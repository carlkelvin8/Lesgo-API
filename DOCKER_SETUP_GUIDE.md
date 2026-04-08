# Docker Setup Guide for Mobile Developers

## Quick Start (5 Minutes)

### Prerequisites
- Docker Desktop installed ([Download](https://www.docker.com/products/docker-desktop))
- Git installed
- 4GB RAM available
- 10GB disk space

### Setup Steps

#### Windows
```bash
# 1. Clone the repository
git clone <repository-url>
cd lesgo-api

# 2. Run setup script
docker-setup.bat
```

#### Mac/Linux
```bash
# 1. Clone the repository
git clone <repository-url>
cd lesgo-api

# 2. Make script executable
chmod +x docker-setup.sh

# 3. Run setup script
./docker-setup.sh
```

### That's It! 🎉

The API is now running at: **http://localhost:8000**

---

## What Gets Installed

The Docker setup includes:

1. **Laravel API** (Port 8000)
   - PHP 8.4 with all required extensions
   - Nginx web server
   - All security features enabled

2. **PostgreSQL Database** (Port 5432)
   - Database: `lesgo_db`
   - Username: `postgres`
   - Password: `secret`

3. **Redis Cache** (Port 6379)
   - For sessions and caching
   - Password: `redis_secret`

4. **Adminer** (Port 8080)
   - Web-based database management
   - Access at: http://localhost:8080

---

## Testing the API

### 1. Check if API is Running
```bash
curl http://localhost:8000/api/v1/ping
```

**Expected Response:**
```json
{
  "message": "LeSGo API v1 OK"
}
```

### 2. Register a User
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecureP@ss123",
    "password_confirmation": "SecureP@ss123",
    "role": "customer"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "role": "customer"
  }
}
```

### 3. Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecureP@ss123"
  }'
```

### 4. Access Protected Endpoint
```bash
# Replace YOUR_TOKEN with the token from login/register
curl -X GET http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## API Endpoints

### Base URL
```
http://localhost:8000/api/v1
```

### Public Endpoints (No Authentication)
```
POST   /auth/register     - Register new user
POST   /auth/login        - Login and get token
GET    /services          - List services
GET    /services/{id}     - View service details
```

### Protected Endpoints (Require Bearer Token)
```
GET    /auth/me           - Get current user
PUT    /auth/me           - Update profile
POST   /auth/logout       - Logout
GET    /orders            - List orders
POST   /orders            - Create order
GET    /orders/{id}       - View order
```

### Rate Limits
- Auth endpoints: 5 requests/minute
- Public endpoints: 60 requests/minute
- Authenticated endpoints: 120 requests/minute

---

## Mobile App Integration

### 1. Base Configuration

**Android (Kotlin)**
```kotlin
object ApiConfig {
    const val BASE_URL = "http://10.0.2.2:8000/api/v1/"  // Android Emulator
    // const val BASE_URL = "http://localhost:8000/api/v1/"  // Physical Device
    const val TIMEOUT = 30L // seconds
}
```

**iOS (Swift)**
```swift
struct ApiConfig {
    static let baseURL = "http://localhost:8000/api/v1/"
    static let timeout: TimeInterval = 30
}
```

**React Native**
```javascript
const API_CONFIG = {
  baseURL: 'http://localhost:8000/api/v1/',
  timeout: 30000,
};
```

### 2. Authentication Flow

```
1. User registers/logs in
   ↓
2. App receives token
   ↓
3. Store token securely (Keychain/SharedPreferences)
   ↓
4. Include token in all API requests:
   Header: Authorization: Bearer {token}
   ↓
5. Handle 401 responses (token expired/invalid)
   ↓
6. Redirect to login
```

### 3. Example: Retrofit (Android)

```kotlin
interface ApiService {
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>
    
    @GET("auth/me")
    suspend fun getProfile(
        @Header("Authorization") token: String
    ): Response<UserResponse>
}

// Usage
val token = "Bearer ${savedToken}"
val response = apiService.getProfile(token)
```

### 4. Example: Alamofire (iOS)

```swift
func login(email: String, password: String) {
    let parameters: [String: Any] = [
        "email": email,
        "password": password
    ]
    
    AF.request("\(ApiConfig.baseURL)auth/login",
               method: .post,
               parameters: parameters,
               encoding: JSONEncoding.default)
        .responseJSON { response in
            // Handle response
        }
}
```

### 5. Example: Axios (React Native)

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: API_CONFIG.baseURL,
  timeout: API_CONFIG.timeout,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 responses
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login
    }
    return Promise.reject(error);
  }
);
```

---

## Docker Commands

### Start/Stop
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# View logs
docker-compose logs -f app
```

### Database
```bash
# Run migrations
docker-compose exec app php artisan migrate

# Fresh migration (reset database)
docker-compose exec app php artisan migrate:fresh

# Seed database
docker-compose exec app php artisan db:seed
```

### Debugging
```bash
# Access container shell
docker-compose exec app bash

# View Laravel logs
docker-compose exec app tail -f storage/logs/laravel.log

# Clear cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
```

### Testing
```bash
# Run tests
docker-compose exec app php artisan test

# Run specific test
docker-compose exec app php artisan test --filter=AuthenticationTest
```

---

## Troubleshooting

### API Not Responding
```bash
# Check if containers are running
docker-compose ps

# Check logs
docker-compose logs app

# Restart containers
docker-compose restart
```

### Database Connection Error
```bash
# Check if database is running
docker-compose ps db

# Restart database
docker-compose restart db

# Wait 10 seconds and try again
```

### Port Already in Use
```bash
# Stop containers
docker-compose down

# Change port in docker-compose.yml
# ports:
#   - "8001:80"  # Change 8000 to 8001

# Start again
docker-compose up -d
```

### Permission Errors
```bash
# Fix permissions
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 755 /var/www/html/storage
```

---

## Database Management

### Using Adminer (Web Interface)

1. Open http://localhost:8080
2. Login with:
   - System: PostgreSQL
   - Server: db
   - Username: postgres
   - Password: secret
   - Database: lesgo_db

### Using Command Line

```bash
# Access PostgreSQL
docker-compose exec db psql -U postgres -d lesgo_db

# List tables
\dt

# Query users
SELECT * FROM users;

# Exit
\q
```

---

## Environment Variables

Edit `.env` file to customize:

```env
# Application
APP_NAME="LeSGo API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=lesgo_db
DB_USERNAME=postgres
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=redis_secret
REDIS_PORT=6379

# Security
SANCTUM_TOKEN_EXPIRATION=1440
CORS_ALLOWED_ORIGINS=http://localhost:3000
```

---

## Production Deployment

For production deployment, see:
- `SECURITY_DEPLOYMENT.md` - Security configuration
- `API_SECURITY_EXAMPLES.md` - API examples
- `API_FLOW_DOCUMENTATION.md` - Complete API documentation

---

## Support

### Common Issues

**Q: Can't connect from mobile app**
A: Use correct IP:
- Android Emulator: `http://10.0.2.2:8000`
- iOS Simulator: `http://localhost:8000`
- Physical Device: `http://YOUR_COMPUTER_IP:8000`

**Q: Token expired**
A: Tokens expire after 24 hours. User must login again.

**Q: Rate limit exceeded**
A: Wait 60 seconds or adjust rate limits in `.env`

**Q: Database reset needed**
A: Run `docker-compose exec app php artisan migrate:fresh`

### Get Help

1. Check logs: `docker-compose logs -f app`
2. Review documentation in this repository
3. Contact backend team

---

## Quick Reference

### URLs
- API: http://localhost:8000
- Adminer: http://localhost:8080
- API Docs: http://localhost:8000/api/documentation

### Credentials
- Database: postgres / secret
- Redis: redis_secret

### Important Files
- `docker-compose.yml` - Docker configuration
- `.env` - Environment variables
- `DOCKER_SETUP_GUIDE.md` - This file
- `API_SECURITY_EXAMPLES.md` - API examples

---

**Ready to integrate!** 🚀

The API is fully functional and ready for mobile app development. All security features are enabled and working.
