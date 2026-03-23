# LeSGo API

Laravel 11 REST API for the LeSGo logistics & multi-service platform.

---

## Stack

- PHP 8.4 + Laravel 11
- PostgreSQL 16
- Redis 7
- Laravel Reverb (WebSockets)
- Laravel Sanctum (auth)
- Docker (full stack)
- ngrok (public tunnel for local dev)

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [PHP 8.4](https://www.php.net/downloads) + Composer (for running outside Docker)
- [Node.js 20+](https://nodejs.org/) (for Playwright tests)
- [ngrok](https://ngrok.com/download) (for public URL)
- Git

---

## Quick Start

### 1. Clone the repo

```bash
git clone https://github.com/carlkelvin8/Lesgo-API.git
cd Lesgo-API
git checkout feature/auth
```

### 2. Set up environment

```bash
cp .env.example .env
```

Edit `.env` with these minimum values for local dev:

```env
APP_NAME=LeSGo
APP_ENV=local
APP_KEY=                        # generated in step 4
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lesgo_db
DB_USERNAME=postgres
DB_PASSWORD=secret

CACHE_STORE=array
QUEUE_CONNECTION=database
SESSION_DRIVER=database

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=redis_secret

REVERB_APP_ID=lesgo-app
REVERB_APP_KEY=lesgo-reverb-key
REVERB_APP_SECRET=lesgo-reverb-secret
REVERB_HOST=localhost
REVERB_PORT=8081
REVERB_SCHEME=http
```

### 3. Start Docker

```bash
docker compose up -d
```

This starts:

| Container        | Port  | Description              |
|------------------|-------|--------------------------|
| lesgo-api        | 8000  | Laravel app              |
| lesgo-db         | 5432  | PostgreSQL 16            |
| lesgo-redis      | 6379  | Redis 7                  |
| lesgo-adminer    | 8082  | DB admin UI              |
| lesgo-queue      | —     | Queue worker             |
| lesgo-reverb     | 8081  | WebSocket server         |
| lesgo-scheduler  | —     | Cron scheduler           |

### 4. Install dependencies & run migrations

```bash
composer install --ignore-platform-reqs
php artisan key:generate
php artisan migrate
php artisan swagger:generate
```

### 5. Start the dev server (outside Docker)

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Verify it's running:
```
http://127.0.0.1:8000/api/v1/ping
```

---

## Expose with ngrok

ngrok creates a public HTTPS URL so Flutter or other devices can reach your local API.

### Install ngrok

**Windows (winget):**
```powershell
winget install ngrok.ngrok
```

**macOS:**
```bash
brew install ngrok/ngrok/ngrok
```

**Linux:**
```bash
curl -sSL https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update && sudo apt install ngrok
```

### Configure your authtoken

Sign up free at [https://ngrok.com](https://ngrok.com), then grab your token from [https://dashboard.ngrok.com/get-started/your-authtoken](https://dashboard.ngrok.com/get-started/your-authtoken).

```bash
ngrok config add-authtoken <YOUR_TOKEN>
```

### Start the tunnel

Make sure `php artisan serve` is running first, then:

```bash
ngrok http 8000
```

You'll get a URL like:
```
https://abc123.ngrok-free.app
```

Your API is now publicly accessible at:
```
https://abc123.ngrok-free.app/api/v1
```

> **Note:** On the free plan the URL changes every time you restart ngrok. Keep the terminal open while developing.

### Flutter base URL

```dart
const String baseUrl = 'https://abc123.ngrok-free.app/api/v1';

// Required headers for all requests
headers: {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'ngrok-skip-browser-warning': 'true',
}
```

---

## API Documentation (Swagger)

Once the server is running, open:

```
http://127.0.0.1:8000/api/documentation
```

Or via ngrok:
```
https://abc123.ngrok-free.app/api/documentation
```

To regenerate docs after annotation changes:
```bash
php artisan swagger:generate
```

---

## Running Tests

### Playwright API tests (66 tests)

```bash
cd playwright
npm install
npx playwright test --reporter=list
```

### Performance tests only

```bash
npx playwright test --project=performance --reporter=list
```

### PHPUnit tests

```bash
php artisan test
```

---

## Key Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Register (customer/driver/partner_admin) |
| POST | `/api/v1/auth/login` | Login, returns Sanctum token |
| GET | `/api/v1/auth/me` | Current user |
| GET | `/api/v1/orders` | List orders (scoped by role) |
| POST | `/api/v1/orders` | Create order |
| PATCH | `/api/v1/orders/{id}/status` | Update order status |
| GET | `/api/v1/payments` | List payments |
| POST | `/api/v1/payments` | Record payment |
| GET | `/api/v1/services` | List services (public) |
| GET | `/api/v1/wallets/{userId}` | Wallet balance |
| POST | `/api/v1/webhooks/payments/{provider}` | Xendit/GCash/Maya webhook |

All responses follow the format:
```json
{
  "success": true,
  "message": "...",
  "request_id": "uuid",
  "data": {}
}
```

---

## Roles

| Role | Access |
|------|--------|
| `customer` | Own orders, payments, addresses, wallet |
| `driver` | Own profile, assigned orders, location updates |
| `partner_admin` | Partner orders, branches, drivers |
| `admin` | Full access |

---

## Useful Commands

```bash
# Clear all API cache
php artisan cache:clear-api

# Generate Swagger docs
php artisan swagger:generate

# Cache config & routes (production)
php artisan config:cache
php artisan route:cache

# Run queue worker manually
php artisan queue:work --queue=default,notifications,audit

# Monitor failed jobs
php artisan jobs:monitor
```

---

## Database Admin

Adminer is available at `http://127.0.0.1:8082`

- System: PostgreSQL
- Server: `db`
- Username: `postgres`
- Password: `secret`
- Database: `lesgo_db`
