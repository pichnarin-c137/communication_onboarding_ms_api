<?php

namespace App\Services\Crm;

use App\Exceptions\Business\CrmDealNotFoundException;
use App\Exceptions\Business\DealAlreadyClosedException;
use App\Exceptions\Business\InvalidDealStageTransitionException;
use App\Models\Client;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Services\Logging\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrmDealService
{
    private const PIPELINE_CACHE_KEY_ALL = 'crm:pipeline:stats:all';

    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  string|null  $scopeUserId  When set, restrict to deals assigned to this user
     *                                     (sale role). Null = no scope (admin sees all).
     */
    public function list(array $filters, int $perPage, int $page, ?string $scopeUserId = null): array
    {
        $query = CrmDeal::query()
            ->with(['assignee:id,first_name,last_name', 'creator:id,first_name,last_name'])
            ->orderByDesc('created_at');

        if ($scopeUserId !== null) {
            $query->where('assigned_to', $scopeUserId);
        }

        if (! empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('crm_contact_id', $filters['contact_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (CrmDeal $d) => CrmPresenter::deal($d))
                ->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @throws CrmDealNotFoundException
     */
    public function get(string $id): CrmDeal
    {
        $deal = CrmDeal::query()
            ->with(['assignee:id,first_name,last_name', 'creator:id,first_name,last_name'])
            ->find($id);

        if (! $deal) {
            throw new CrmDealNotFoundException(
                "CRM deal with ID '$id' not found.",
                context: ['crm_deal_id' => $id]
            );
        }

        return $deal;
    }

    public function create(array $data, ?string $createdBy): CrmDeal
    {
        $deal = DB::transaction(function () use ($data, $createdBy): CrmDeal {
            $deal = CrmDeal::create([
                'crm_contact_id' => $data['contact_id'],
                'title' => $data['title'],
                'stage' => $data['stage'] ?? 'prospect',
                'value' => $data['value'] ?? null,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $createdBy,
            ]);

            $this->recomputeContactStatus($deal->crm_contact_id);

            return $deal;
        });

        $this->invalidatePipelineCache($deal->assigned_to);

        $this->activityLogger->log(
            ActivityLogger::CRM_DEAL_CREATED,
            "CRM deal '$deal->title' created",
            ['crm_deal_id' => $deal->id, 'crm_contact_id' => $deal->crm_contact_id],
            $createdBy,
        );

        return $this->get($deal->id);
    }

    /**
     * @throws CrmDealNotFoundException
     * @throws InvalidDealStageTransitionException
     */
    public function update(string $id, array $data): CrmDeal
    {
        $deal = $this->get($id);

        if ($this->isTerminal($deal->stage)) {
            throw new InvalidDealStageTransitionException(
                "Deal is already '{$deal->stage}' and can no longer be edited.",
                context: ['crm_deal_id' => $id, 'stage' => $deal->stage]
            );
        }

        $previousAssignee = $deal->assigned_to;

        DB::transaction(function () use ($deal, $data): void {
            $deal->update($data);
            $this->recomputeContactStatus($deal->crm_contact_id);
        });

        // Reassigning a deal moves it between two sales' boards — clear both.
        $this->invalidatePipelineCache($previousAssignee, $deal->assigned_to);

        $this->activityLogger->log(
            ActivityLogger::CRM_DEAL_UPDATED,
            "CRM deal '$deal->title' updated",
            ['crm_deal_id' => $deal->id],
        );

        return $this->get($id);
    }

    /**
     * Mark a deal won and sync the linked contact into the COMS `clients` table.
     * Reuses the contact's previously-synced client to avoid duplicate dropdown entries.
     *
     * @throws CrmDealNotFoundException
     * @throws DealAlreadyClosedException
     */
    public function markWon(string $id, string $callerUserId): array
    {
        $result = DB::transaction(function () use ($id, $callerUserId): array {
            $deal = CrmDeal::query()->lockForUpdate()->find($id);

            if (! $deal) {
                throw new CrmDealNotFoundException(
                    "CRM deal with ID '$id' not found.",
                    context: ['crm_deal_id' => $id]
                );
            }

            if ($this->isTerminal($deal->stage)) {
                throw new DealAlreadyClosedException(
                    "Deal is already '{$deal->stage}' and cannot be marked won.",
                    context: ['crm_deal_id' => $id, 'stage' => $deal->stage]
                );
            }

            /** @var CrmContact $contact */
            $contact = CrmContact::query()->lockForUpdate()->findOrFail($deal->crm_contact_id);

            $ownerId = $deal->assigned_to ?? $callerUserId;

            if ($contact->synced_client_id) {
                $client = Client::find($contact->synced_client_id);
            } else {
                $client = null;
            }

            if (! $client) {
                $client = Client::create([
                    'code' => $this->generateClientCode(),
                    'company_name' => $contact->company_name,
                    'phone_number' => $contact->phone,
                    'email' => $contact->email,
                    'assigned_sale_id' => $ownerId,
                    'is_active' => true,
                ]);
                $contact->synced_client_id = $client->id;
            }

            $deal->stage = 'won';
            $deal->won_at = now();
            $deal->client_id = $client->id;
            $deal->save();

            $contact->status = $this->computeContactStatus($contact->id);
            $contact->save();

            return ['deal' => $deal, 'client' => $client];
        });

        $this->invalidatePipelineCache($result['deal']->assigned_to);

        $this->activityLogger->log(
            ActivityLogger::CRM_DEAL_WON,
            "CRM deal '{$result['deal']->title}' marked won; client synced",
            [
                'crm_deal_id' => $result['deal']->id,
                'client_id' => $result['client']->id,
            ],
            $callerUserId,
        );

        return $result;
    }

    /**
     * @throws CrmDealNotFoundException
     * @throws DealAlreadyClosedException
     */
    public function markLost(string $id, ?string $reason): CrmDeal
    {
        $deal = DB::transaction(function () use ($id, $reason): CrmDeal {
            $deal = CrmDeal::query()->lockForUpdate()->find($id);

            if (! $deal) {
                throw new CrmDealNotFoundException(
                    "CRM deal with ID '$id' not found.",
                    context: ['crm_deal_id' => $id]
                );
            }

            if ($this->isTerminal($deal->stage)) {
                throw new DealAlreadyClosedException(
                    "Deal is already '{$deal->stage}' and cannot be marked lost.",
                    context: ['crm_deal_id' => $id, 'stage' => $deal->stage]
                );
            }

            $deal->stage = 'lost';
            $deal->lost_at = now();
            $deal->lost_reason = $reason;
            $deal->save();

            $this->recomputeContactStatus($deal->crm_contact_id);

            return $deal;
        });

        $this->invalidatePipelineCache($deal->assigned_to);

        $this->activityLogger->log(
            ActivityLogger::CRM_DEAL_LOST,
            "CRM deal '$deal->title' marked lost",
            ['crm_deal_id' => $deal->id, 'lost_reason' => $reason],
        );

        return $this->get($deal->id);
    }

    /**
     * Advance a deal into the demo_scheduled stage when its demo appointment is
     * booked. Only moves a still-open deal forward — never downgrades a deal that
     * is already further along (proposal_sent, negotiating) or terminal.
     */
    public function markDemoScheduled(string $dealId): void
    {
        $ownerId = DB::transaction(function () use ($dealId): ?string {
            $deal = CrmDeal::query()->lockForUpdate()->find($dealId);

            if (! $deal || $deal->stage !== 'prospect') {
                return null;
            }

            $deal->stage = 'demo_scheduled';
            $deal->save();

            $this->recomputeContactStatus($deal->crm_contact_id);

            return $deal->assigned_to ?? '';
        });

        if ($ownerId !== null) {
            $this->invalidatePipelineCache($ownerId === '' ? null : $ownerId);
        }
    }

    /**
     * Stamp a deal when its linked demo appointment completes. The deal stays in
     * the funnel — the sale owner decides whether to win or lose it next.
     * Returns the deal (with assignee) so the caller can notify the owner, or null
     * if the deal no longer exists / is already terminal.
     */
    public function recordDemoCompleted(string $dealId): ?CrmDeal
    {
        return DB::transaction(function () use ($dealId): ?CrmDeal {
            $deal = CrmDeal::query()->lockForUpdate()->find($dealId);

            if (! $deal || $this->isTerminal($deal->stage)) {
                return null;
            }

            $deal->demo_completed_at = now();
            $deal->save();

            return $deal->load('assignee:id,first_name,last_name');
        });
    }

    /**
     * Pipeline totals per stage, zero-filled for every known stage.
     */
    public function pipelineStats(?string $scopeUserId = null): array
    {
        $ttl = config('coms.cache.crm_pipeline_ttl', 300);
        $cacheKey = $this->pipelineCacheKey($scopeUserId);

        return Cache::remember($cacheKey, $ttl, function () use ($scopeUserId): array {
            $rows = CrmDeal::query()
                ->when($scopeUserId !== null, fn ($q) => $q->where('assigned_to', $scopeUserId))
                ->selectRaw('stage, COUNT(*) as count, COALESCE(SUM(value), 0) as total_value')
                ->groupBy('stage')
                ->get()
                ->keyBy('stage');

            $stages = array_map(function (string $stage) use ($rows): array {
                $row = $rows->get($stage);

                return [
                    'stage' => $stage,
                    'count' => (int) ($row->count ?? 0),
                    'total_value' => (float) ($row->total_value ?? 0),
                ];
            }, config('coms.crm.deal_stages'));

            return ['stages' => $stages];
        });
    }

    private function isTerminal(string $stage): bool
    {
        return in_array($stage, config('coms.crm.deal_terminal_stages'), true);
    }

    private function recomputeContactStatus(string $contactId): void
    {
        CrmContact::query()
            ->whereKey($contactId)
            ->update(['status' => $this->computeContactStatus($contactId)]);
    }

    /**
     * Derive a contact's status from its deals:
     *   any won -> won; else any active -> deal_active; else any lost -> lost; else prospect.
     */
    private function computeContactStatus(string $contactId): string
    {
        $stages = CrmDeal::query()
            ->where('crm_contact_id', $contactId)
            ->pluck('stage');

        if ($stages->contains('won')) {
            return 'won';
        }

        if ($stages->contains(fn (string $s) => ! $this->isTerminal($s))) {
            return 'deal_active';
        }

        if ($stages->contains('lost')) {
            return 'lost';
        }

        return 'prospect';
    }

    private function generateClientCode(): string
    {
        $prefix = config('coms.crm.client_code_prefix', 'CL');
        $date = now()->format('Ymd');

        for ($i = 0; $i < 10; $i++) {
            $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5));
            $code = "$prefix-$date-$random";

            if (! Client::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Failed to generate unique client code after 10 attempts.');
    }

    private function pipelineCacheKey(?string $scopeUserId): string
    {
        return $scopeUserId === null
            ? self::PIPELINE_CACHE_KEY_ALL
            : "crm:pipeline:stats:sale_$scopeUserId";
    }

    /**
     * Invalidate the admin (all) aggregate plus every affected owner's scoped
     * aggregate. Pass both the old and new assignee when a deal is reassigned.
     */
    private function invalidatePipelineCache(?string ...$ownerIds): void
    {
        Cache::forget(self::PIPELINE_CACHE_KEY_ALL);

        foreach (array_unique(array_filter($ownerIds)) as $ownerId) {
            Cache::forget($this->pipelineCacheKey($ownerId));
        }
    }
}
