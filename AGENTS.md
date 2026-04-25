# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

COMS (Customer Onboarding Management System) for CheckinMe — a Laravel 12 API managing customer training workflows. Sales create onboarding requests, assign Trainers, and Trainers conduct sessions with client contacts across multi-stage training programs (Initial Setup → Basic Training → Advanced Training → Assessment → Handover).

## Before Implementation read those in the sequence order
- 1. `docs/COMS_Implementation_Rules.md`
- 2. `docs/COMS_Claude_Code_Prompt.md`
- 3. `docs/COMS_Claude_Code_Prompt_Part2.md`

Roles: `sale`, `trainer`, `admin`

## Key Commands

```bash
# Development
./dev.sh                             # Start Laravel + queue worker + cloudflared tunnel + Vite (all at once)
php artisan serve
php artisan migrate
php artisan migrate:fresh --seed
php artisan jwt:generate-keys        # Generate RS256 key pair (required on fresh setup)
php artisan queue:work               # Process queued Telegram notifications and broadcast events (async)

# Testing
php artisan test                     # Run all tests
php artisan test --testsuite=Unit    # Run only Unit tests
php artisan test --testsuite=Feature # Run only Feature tests
php artisan test tests/Unit/Services/NotificationServiceTest.php  # Run a single file
php artisan test --filter=it_creates_one_notification_row_per_user  # Run a single test by name
```

JWT keys are stored in `storage/keys/` (`jwt_private.pem`, `jwt_public.pem`). The `docker-compose.yml` mounts this directory as a volume. Tests auto-generate keys if absent (via `TestCase::ensureJwtKeysExist()`).

Tests use `RefreshDatabase` and seed roles (`admin`, `user`) before each test via `TestCase::seedRoles()`. The test database is the same PostgreSQL instance (`coms_dev`) — there is no separate test DB configured.

---

## Architecture

### Domain Layers
- **Appointment layer**: `appointments` + `appointment_students` + `appointment_materials`
  Services: `AppointmentService`, `AppointmentStatusService`, `AppointmentConflictService`, `DemoCompletionService`
- **Onboarding layer**: `onboarding_requests` + `onboarding_company_info` + `onboarding_system_analysis` + `onboarding_policies` + `onboarding_lessons`
  Services: `OnboardingService`, `OnboardingTriggerService`, `OnboardingProgressService`, `LessonSendService`

`OnboardingTriggerService` auto-creates an `onboarding_request` when a training `appointment` is completed. No manual creation endpoint exists.

### Three-Layer Pattern
**Controllers** → **Services** → **Models (Eloquent)**

All key services are registered as **singletons** in `AppServiceProvider`: `ActivityLogger`, `NotificationService`, `TelegramService`, `AppointmentConflictService`, `AppointmentStatusService`, `DemoCompletionService`, `OnboardingTriggerService`, `AppointmentService`, `OnboardingProgressService`, `LessonSendService`, `OnboardingService`, `Pusher`.

### Authentication — Custom JWT (no `Auth::user()`)
Auth uses a custom RS256 JWT implementation (`JwtService`), not Laravel's built-in auth. The `jwt.auth` middleware (`JwtAuthenticate`) decodes the token and sets two values on the request:
- `$request->get('auth_user_id')` — UUID of the authenticated user
- `$request->get('auth_role')` — role string (`sale`, `trainer`, `admin`)

**Never call `Auth::user()` or `$request->user()` in controllers.** Always read from `$request->get('auth_user_id')` and `$request->get('auth_role')`.

The `role:sale,admin` middleware (`RoleMiddleware`) reads `auth_role` — it does not touch `Auth`.

### Route Structure
All routes under `/api/v1/`. Key middleware groups:
- Public: `POST /auth/login`, `POST /auth/verify-otp`, `POST /auth/refresh-token`
- `jwt.auth` + `throttle:api`: everything else
- `role:admin`: user management CRUD
- `role:sale,admin`: Telegram management (`/telegram/...`)
- `POST /telegram/webhook`: unauthenticated, verified by `VerifyTelegramWebhookSecret` middleware
- `POST /broadcasting/auth`: JWT-authenticated, custom Pusher channel auth (does NOT use `Broadcast::auth()`)

### Exception Hierarchy
`BaseException` (abstract, carries HTTP status code) → typed exceptions in `app/Exceptions/`. Business domain exceptions live in `app/Exceptions/Business/` (`InvalidStatusTransitionException`, `AppointmentLockedException`, `AppointmentTimeTooEarlyException`, `TrainerScheduleConflictException`, `LessonLockedAfterSendException`, `DefaultPolicyCannotBeRemovedException`, `OnboardingProgressTooLowException`, `ProofRequiredException`, `LeaveOfficeNotAllowedException`, `DemoCreationForbiddenException`).

### Notifications (two channels)
**In-app**: `NotificationService::notify()` bulk-inserts `Notification` rows, then dispatches `NotificationCreated` events (fetched by pre-generated UUID after insert). Events are broadcast via Pusher to `private-notifications.{userId}` channels.

**Telegram**: `TelegramGroupService::notifyClient()` → `sendMessage()` → dispatches `SendTelegramNotification` queued job. Message bodies are rendered by `TelegramMessageTemplate` using language files in `resources/lang/{en,km}/telegram.php`.

Both channels fail quietly (caught + logged) — failures must never break the core operation.

### Telegram Group Bot Status
`bot_status` values: `connected` → `removed` (via disconnect or `bot_removed` webhook event), `reconnected` (via manual reconnect). The `scopeConnected()` scope and `sendTestMessage()` accept both `connected` and `reconnected` as active states.

`/setup TOKEN` webhook detection handles `@botname` suffix appended by Telegram in group chats.

### Real-time Broadcasting
Pusher (cluster `ap1`) is used for real-time notifications. `BroadcastController::auth()` handles private channel auth using `auth_user_id` from JWT — it only authorizes `private-notifications.{userId}` channels. The `Pusher` instance is registered as a singleton in `AppServiceProvider` from `config/services.pusher`.

### Async Queue
`SendTelegramNotification` and `NotificationCreated` broadcasts both use `ShouldQueue`. Requires `php artisan queue:work` to be running. Queue is database-backed (`jobs` table).

### UUID Convention
**All tables use `uuid('id')->primary()`** — never `$table->id()`. All FK columns referencing UUID PKs must also be `uuid` type. Every model must declare:
```php
use HasUuids;
public $incrementing = false;
protected $keyType = 'string';
protected $casts = ['id' => 'string', ...all uuid FK columns => 'string'];
```

### Central Config (`config/coms.php`)
All Redis cache TTLs, rate limit thresholds, and business rule constants (e.g. `onboarding_completion_threshold`, `broadcast_queue`) are defined here — never hardcoded inline. Override via `.env` (e.g. `COMS_APPOINTMENT_LIST_TTL`, `COMS_ONBOARDING_COMPLETION_THRESHOLD`).

### Test Helpers
`tests/TestCase.php` provides via traits:
- `createUser(['role' => 'sale'])` — creates User + Credential + Role
- `createAdmin()` — shorthand for admin user
- `authHeadersFor($user)` — returns `['Authorization' => 'Bearer ...']` headers for use in `postJson()`/`getJson()`
- `generateAccessToken($user)` — raw token string

---

### Response Format
Every endpoint must return this structure — no exceptions:

**Success:**
```json
{ "success": true, "message": "...", "data": {} }
```
**Error:**
```json
{ "success": false, "message": "...", "error_code": "SNAKE_CASE_CODE", "errors": {} }
```
- `error_code` is a stable machine-readable string the frontend maps to UI behavior.
- `errors` is only present on 422 validation failures (field-level messages).
- Never return 200 for errors. Never expose stack traces, SQL errors, or file paths.

### Service Isolation Rule
**Never call one Service directly from another Service.** Use Events or orchestrate from a higher-level Action class. Cross-service calls create tight coupling and violate single-responsibility.

### Cache Key Convention
```
{resource}:{identifier}:{variant}
e.g.  appointment:list:trainer_{uuid}
      onboarding:progress:{onboarding_uuid}
```
Invalidate all related keys on every CREATE / UPDATE / DELETE / STATUS CHANGE. Never cache sensitive data in Redis.

### Appointment Business Rules
- `demo` type: only Sale can create. Trainer attempting to create demo → `DemoCreationForbiddenException` (403).
- `training` type: title is auto-generated from `client.company_code + company_name + company_contact`.
- Fields are freely editable while `status = pending`. Once status leaves pending, **only status transitions are allowed** (Admin overrides this).
- Status transitions are location-type-dependent: `physical`/`hybrid` allow `leave_office`; `online` skips it entirely.
- `in_progress` transition requires `start_proof_media_id` + GPS + current time ≥ `scheduled_start_time`.
- `done` transition requires `end_proof_media_id` + GPS + `student_count`.
- `rescheduled` resets the appointment back to `pending` with new date/time.

### Onboarding Trigger Conditions (all three must be true)
```
appointment_type        = 'training'
is_onboarding_triggered = false
is_continued_session    = false
```
When triggered: auto-creates `onboarding_request`, seeds 3 default policies, creates empty `company_info` + `system_analysis` records — all in one DB transaction.

### Onboarding Progress (4 sections → percentage)
| Section | Tasks |
|---------|-------|
| Company Information | 1 task — trainer marks complete |
| System Analysis | 3 tasks — `import_employee`, `connected_app`, `profile_mobile` > 0 |
| HR Policies | 1 task per policy — checked by trainer |
| Lesson Paths | 1 task per lesson — done when sent via Telegram |

`percentage = (completed / total) * 100`. Toggle `complete` requires ≥ 90% (from `config/coms.php`). Sent lessons are immutable (`LessonLockedAfterSendException`). Default policies cannot be deleted (`DefaultPolicyCannotBeRemovedException`).

### GET Endpoint Response Rules (performance)
Every GET endpoint must return only the fields the caller actually needs — no full model dumps of related objects.

**Eager loading — always use column constraints:**
```php
// BAD — dumps entire User/Client models
Appointment::with(['trainer', 'client', 'creator']);

// GOOD — only the fields needed
Appointment::with([
    'trainer:id,first_name,last_name',
    'client:id,company_code,company_name',
    'creator:id,first_name,last_name',
]);
```

**List endpoints vs. show endpoints:**
- List endpoints load minimal relation data (id + display name only). Never load deep chains like `client.sales.createdBy` on a list — use a dedicated endpoint for that.
- Show endpoints may load more but still constrain user/lookup relations to `id,first_name,last_name`.

**Never use `$appends` for data that is already available via an explicit relation.** Appended attributes serialize into every response that touches the model (list, show, status updates) and create hidden duplication. Expose derived data only from the controller or a dedicated endpoint.

**`with()` on `findOrFail()` is wasted if you immediately re-query the same relation.** Either use the already-loaded collection (`$model->relation`) or add `with()` to the second query — never both.

**Mapping to specific fields:** For endpoints that return nested user objects only for display (names, IDs), map in the controller or service to a plain array instead of returning the full Eloquent model:
```php
->map(fn($a) => [
    'trainer_id'   => $a->trainer_id,
    'trainer_name' => $a->trainer ? "{$a->trainer->first_name} {$a->trainer->last_name}" : null,
    'assigned_at'  => $a->assigned_at,
]);
```

---
