# Employee Management System + RSA based two factor Authenticatin

A Laravel 12 application implementing employee management api and secure RSA-based JWT authentication with role-based access control (RBAC) and two-factor authentication (2FA) and Man.

## Features

-  **RSA-based JWT Authentication** - RS256 algorithm with 4096-bit RSA keys
-  **Two-Factor Authentication (2FA)** - Email-based OTP with random 5-10 minute expiry
-  **Role-Based Access Control (RBAC)** - Admin and user roles with middleware protection
-  **Token Management** - Access tokens (1 day) and refresh tokens (30 days)
-  **Separated Credential Storage** - User data separate from authentication credentials
-  **Docker Support** - Complete Docker Compose setup with PostgreSQL
-  **Postman Collection** - Ready-to-use API collection for team collaboration

## Tech Stack

- **Backend:** Laravel 12 (PHP 8.4+)
- **Authentication:** firebase/php-jwt (RS256)
- **Database:** PostgreSQL 16 (Docker), SQLite (local alternative)

## Quick Start

### Docker

```bash
# Start containers
docker-compose up --build -d

# Backend must be at http://localhost:1134
# MySql must be on on localhost:1143
```

## Default Admin Account

- **Username:** `admin`
- **Password:** `Admin@123456`
- **Email:** `your-email@gmail.com`

## API Testing

### Using Postman

1. **Import Collection:**
   - Open Postman
   - Import `EMPLOYEE_MANAGEMENT_Auth.postman_collection.json`
   - Import `EMPLOYEE_MANAGEMENT_Auth.postman_environment.json`

2. **Select Environment:**
   - Choose "EMPLOYEE_MANAGEMENT Auth - Local"

3. **Test Flow:**
   - Login → Get OTP from logs → Verify OTP → Access protected endpoints

### Using cURL

## API Endpoints

### Public Endpoints
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login (sends OTP)
- `POST /api/auth/verify-otp` - Verify OTP and get tokens
- `POST /api/auth/refresh-token` - Refresh access token

### Protected Endpoints
- `POST /api/auth/logout` - Logout and revoke token
- `GET /api/get-profile` - Get user profile

### Admin-Only Endpoints
- `POST /api/create-user` - Admin creates new user
- `GET` /api/get-users - Admin get list of users that have Build query Apply filters Apply pagination Load relationships Map response structure
- `DETELE` /api/soft-delete-user/{userId} - Admin soft delete user by id
- `DELETE` /api/hard-delete-user/{userId} - Admin hard delte user by id
- `PATCH` /api/restore-user/{userId} - Admin restore soft delete user by id
- `PATCH` /api/update-user-information/{userId} - Admin update user information


## Docker Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f backend
docker-compose logs -f postgres

# Access backend shell
docker-compose exec backend bash

#Configuration shell
docker compose exec backend /bin/bash #For docker
railway ssh --project=project-id --environment=environment-id --service=service-id #For railway

## Project Structure

```
app/
├── Console/Commands/     # Artisan commands (GenerateJwtKeys)
├── Http/
│   ├── Controllers/      # API controllers (AuthController, UserController)
│   └── Middleware/       # Custom middleware (JwtAuthenticate, AdminOnly)
├── Models/               # Eloquent models (User, Credential, RefreshToken, Role)
└── Services/             # Service classes (JwtService, OtpService)

database/
├── migrations/           # Database migrations
└── seeders/              # Database seeders

storage/
└── keys/                 # RSA keys (jwt_private.pem, jwt_public.pem)

resources/
├── css/                  # Stylesheets (TailwindCSS)
├── js/                   # JavaScript/frontend code
└── views/                # Blade templates

tests/
├── Feature/              # Feature tests
└── Unit/                 # Unit tests
```

## Security Features

- **Password Hashing:** bcrypt with 12 rounds
- **Token Security:** RS256 asymmetric encryption with 4096-bit RSA keys
- **Token Expiry:** Access tokens (1 day), Refresh tokens (30 days)
- **OTP Security:** 4-digit OTP with 5-10 minute random expiry
- **Account Suspension:** Admin capability to suspend users
- **Data Protection:** No password/OTP exposure in API responses
- **Password Requirements:** Minimum 8 chars with uppercase, lowercase, number, and special character

## Database Architecture

- **roles** - Role definitions (admin, user)
- **users** - User profile information
- **credentials** - Authentication data (username, email, password, OTP)
- **refresh_tokens** - JWT refresh token storage
- **sessions** - Session tracking
- **cache** - Cache storage
- **jobs** - Queue jobs
- **personal_information** - User's personal information (Document related)
- **emergency_contact** - User's personal emergency person

All tables use UUID primary keys for enhanced security.

## Environment Configuration

### Docker Mode (.env)
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=employee_db
DB_USERNAME=employee_user
DB_PASSWORD=employee_secure_pass_2026
```

---

# communication_onboarding_ms_api
