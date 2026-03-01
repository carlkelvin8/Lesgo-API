# LeSGo API

Enterprise-grade Laravel REST API with comprehensive security features and Docker deployment.

## Quick Start

### Docker Setup (Recommended)

**Windows:**
```bash
docker-setup.bat
```

**Mac/Linux:**
```bash
chmod +x docker-setup.sh
./docker-setup.sh
```

**API Ready:** http://localhost:8000

## Features

- Laravel 11 with Sanctum authentication
- PostgreSQL 16 database
- Redis 7 cache
- Enterprise-level security (OWASP Top 10 compliant)
- Rate limiting (5/60/120 req/min)
- Docker deployment ready
- Postman collection included

## API Endpoints

**Base URL:** `http://localhost:8000/api/v1`

### Public
- POST `/auth/register` - Register user
- POST `/auth/login` - Login
- GET `/services` - List services

### Protected (Bearer Token)
- GET `/auth/me` - Get current user
- PUT `/auth/me` - Update profile
- POST `/auth/logout` - Logout
- GET `/orders` - List orders
- POST `/orders` - Create order

## Testing

Import `postman_collection.json` into Postman for complete API testing.

## Tech Stack

- Laravel 11
- PHP 8.4
- PostgreSQL 16
- Redis 7
- Nginx
- Docker

## License

MIT License
