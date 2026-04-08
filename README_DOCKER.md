# LeSGo API - Docker Setup for Mobile Developers

## 🚀 Quick Start (2 Commands)

### Windows
```bash
git clone <repository-url> && cd lesgo-api && docker-setup.bat
```

### Mac/Linux
```bash
git clone <repository-url> && cd lesgo-api && chmod +x docker-setup.sh && ./docker-setup.sh
```

**That's it!** API is running at http://localhost:8000

---

## 📱 For Mobile Developers

### What You Get
- ✅ Fully functional Laravel API
- ✅ PostgreSQL database (pre-configured)
- ✅ Redis cache (for sessions)
- ✅ All security features enabled
- ✅ Database management UI (Adminer)
- ✅ No manual configuration needed

### API Base URL
```
http://localhost:8000/api/v1
```

### Test It Works
```bash
curl http://localhost:8000/api/v1/ping
```

Expected response:
```json
{"message": "LeSGo API v1 OK"}
```

---

## 📖 Documentation

| Document | Description |
|----------|-------------|
| `DOCKER_SETUP_GUIDE.md` | Complete setup and integration guide |
| `API_SECURITY_EXAMPLES.md` | All API endpoints with examples |
| `API_FLOW_DOCUMENTATION.md` | API architecture and flows |
| `postman_collection.json` | Import into Postman for testing |

---

## 🔑 Quick API Test

### 1. Register User
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecureP@ss123",
    "password_confirmation": "SecureP@ss123",
    "role": "customer"
  }'
```

### 2. Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecureP@ss123"
  }'
```

### 3. Use Token
```bash
curl -X GET http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🔧 Common Commands

```bash
# Start API
docker-compose up -d

# Stop API
docker-compose down

# View logs
docker-compose logs -f app

# Reset database
docker-compose exec app php artisan migrate:fresh

# Run tests
docker-compose exec app php artisan test
```

---

## 📱 Mobile App Configuration

### Android (Kotlin)
```kotlin
const val BASE_URL = "http://10.0.2.2:8000/api/v1/"  // Emulator
// const val BASE_URL = "http://YOUR_IP:8000/api/v1/"  // Physical device
```

### iOS (Swift)
```swift
let baseURL = "http://localhost:8000/api/v1/"
```

### React Native
```javascript
const BASE_URL = 'http://localhost:8000/api/v1/';
```

---

## 🌐 Access Points

| Service | URL | Credentials |
|---------|-----|-------------|
| API | http://localhost:8000 | - |
| Database UI | http://localhost:8080 | postgres / secret |
| PostgreSQL | localhost:5432 | postgres / secret |
| Redis | localhost:6379 | redis_secret |

---

## ❓ Troubleshooting

### API not responding?
```bash
docker-compose ps  # Check if running
docker-compose logs app  # Check logs
docker-compose restart  # Restart
```

### Database error?
```bash
docker-compose restart db
# Wait 10 seconds
docker-compose exec app php artisan migrate
```

### Port already in use?
Edit `docker-compose.yml` and change port 8000 to 8001:
```yaml
ports:
  - "8001:80"
```

---

## 📚 Full Documentation

See `DOCKER_SETUP_GUIDE.md` for:
- Complete API endpoint list
- Mobile app integration examples
- Authentication flow
- Error handling
- Rate limiting details
- Security features

---

## 🎯 Features

- ✅ **Authentication**: Token-based (Sanctum)
- ✅ **Authorization**: Role-based access control
- ✅ **Validation**: Strong password requirements
- ✅ **Rate Limiting**: Prevents abuse
- ✅ **Security Headers**: CORS, CSP, etc.
- ✅ **Audit Logging**: Track all actions
- ✅ **Error Handling**: Consistent JSON responses
- ✅ **Database**: PostgreSQL with migrations
- ✅ **Cache**: Redis for performance

---

## 🆘 Need Help?

1. Check `DOCKER_SETUP_GUIDE.md`
2. View logs: `docker-compose logs -f app`
3. Test with Postman: Import `postman_collection.json`
4. Contact backend team

---

## 📦 What's Included

```
lesgo-api/
├── Dockerfile                    # Docker image configuration
├── docker-compose.yml            # Services configuration
├── docker-setup.sh               # Setup script (Mac/Linux)
├── docker-setup.bat              # Setup script (Windows)
├── .env.docker                   # Environment template
├── DOCKER_SETUP_GUIDE.md         # Complete guide
├── postman_collection.json       # API testing collection
└── docker/
    ├── nginx/                    # Web server config
    ├── php/                      # PHP config
    └── supervisor/               # Process manager
```

---

## ✅ System Requirements

- **Docker Desktop**: Latest version
- **RAM**: 4GB minimum
- **Disk**: 10GB free space
- **OS**: Windows 10+, macOS 10.14+, or Linux

---

## 🚀 Ready to Develop!

The API is fully configured and ready for mobile app integration. All security features are enabled and working.

**Start developing your mobile app now!** 📱
