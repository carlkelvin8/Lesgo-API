# LeSGo API - Deployment Checklist

## Pre-Deployment Verification

### ✅ Code Quality
- [x] All security features implemented
- [x] Rate limiting configured
- [x] Input validation on all endpoints
- [x] Error handling production-safe
- [x] No sensitive data in responses
- [x] No debug code or console logs
- [x] Code follows Laravel best practices

### ✅ Security
- [x] OWASP Top 10 compliance verified
- [x] Authentication implemented (Sanctum)
- [x] Authorization implemented (Policies/Gates)
- [x] Rate limiting configured (5/60/120 req/min)
- [x] Security headers configured
- [x] CORS properly configured
- [x] Input sanitization active
- [x] Audit logging enabled
- [x] Token expiration configured (24h)
- [x] Password policies enforced
- [x] Failed login tracking enabled
- [x] User enumeration prevented

### ✅ Database
- [x] Migrations created and tested
- [x] Indexes on lookup fields
- [x] Unique constraints on emails
- [x] Foreign keys properly defined
- [x] Soft deletes on sensitive data
- [x] No raw SQL queries (Eloquent only)

### ✅ Configuration
- [x] Environment variables documented
- [x] `.env.example` up to date
- [x] Database connection configured
- [x] Redis connection configured
- [x] Mail configuration ready
- [x] Queue configuration ready
- [x] Logging configuration set

### ✅ Testing
- [x] Test suite created (32 tests)
- [x] Authentication tests written
- [x] Authorization tests written
- [x] Security tests written
- [x] Rate limiting tests written
- [x] Validation tests written
- [x] Manual testing completed

### ✅ Documentation
- [x] README.md complete
- [x] API documentation available
- [x] Docker setup guide created
- [x] Mobile integration guide created
- [x] Project status documented
- [x] Quick start guide created
- [x] Postman collection available

### ✅ Docker
- [x] Dockerfile optimized
- [x] docker-compose.yml configured
- [x] Setup scripts created (Windows/Mac/Linux)
- [x] All services configured (API, DB, Redis, Adminer)
- [x] Volumes configured for persistence
- [x] Networks configured
- [x] Health checks implemented

---

## Deployment Steps

### Option 1: Docker Deployment (Recommended)

#### 1. Server Setup
```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

#### 2. Clone Repository
```bash
git clone <repository-url>
cd lesgo-api
```

#### 3. Configure Environment
```bash
# Copy environment file
cp .env.docker .env

# Edit environment variables
nano .env
```

**Required Changes:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_PASSWORD=<strong-password>
REDIS_PASSWORD=<strong-password>

SANCTUM_TOKEN_EXPIRATION=1440
CORS_ALLOWED_ORIGINS=https://your-frontend.com
```

#### 4. Run Setup
```bash
chmod +x docker-setup.sh
./docker-setup.sh
```

#### 5. Configure Web Server (Nginx/Apache)
```nginx
# Nginx reverse proxy configuration
server {
    listen 80;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### 6. Setup SSL (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

#### 7. Verify Deployment
```bash
curl https://your-domain.com/api/v1/ping
```

---

### Option 2: Manual Deployment

#### 1. Server Requirements
- PHP 8.4+
- PostgreSQL 16+
- Redis 7+
- Nginx or Apache
- Composer
- Git

#### 2. Install Dependencies
```bash
# Clone repository
git clone <repository-url>
cd lesgo-api

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### 3. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=lesgo_db
DB_USERNAME=postgres
DB_PASSWORD=<strong-password>

REDIS_HOST=localhost
REDIS_PASSWORD=<strong-password>
REDIS_PORT=6379
```

#### 4. Setup Database
```bash
# Create database
sudo -u postgres psql
CREATE DATABASE lesgo_db;
CREATE USER lesgo_user WITH PASSWORD '<strong-password>';
GRANT ALL PRIVILEGES ON DATABASE lesgo_db TO lesgo_user;
\q

# Run migrations
php artisan migrate --force
```

#### 5. Optimize Application
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

#### 6. Configure Web Server
```nginx
# Nginx configuration
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/lesgo-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### 7. Setup Process Manager (Supervisor)
```ini
[program:lesgo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lesgo-api/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/lesgo-api/storage/logs/worker.log
```

#### 8. Setup Cron Jobs
```bash
crontab -e
```

Add:
```
* * * * * cd /var/www/lesgo-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## Post-Deployment Verification

### 1. Health Check
```bash
curl https://your-domain.com/api/v1/ping
```

**Expected:**
```json
{
  "message": "LeSGo API v1 OK"
}
```

### 2. Test Authentication
```bash
# Register
curl -X POST https://your-domain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "SecureP@ss123",
    "password_confirmation": "SecureP@ss123",
    "role": "customer"
  }'

# Login
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecureP@ss123"
  }'
```

### 3. Test Rate Limiting
```bash
# Should get 429 after 5 attempts
for i in {1..6}; do
  curl -X POST https://your-domain.com/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"wrong"}'
  echo ""
done
```

### 4. Test Security Headers
```bash
curl -I https://your-domain.com/api/v1/ping
```

**Verify headers:**
- `Strict-Transport-Security`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy`
- `Content-Security-Policy`

### 5. Test CORS
```bash
curl -H "Origin: https://unauthorized-domain.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -X OPTIONS https://your-domain.com/api/v1/auth/login -I
```

**Should NOT include CORS headers for unauthorized origins**

### 6. Check Logs
```bash
# Docker
docker-compose logs -f app

# Manual
tail -f storage/logs/laravel.log
```

### 7. Monitor Performance
```bash
# Check response times
time curl https://your-domain.com/api/v1/ping

# Check database connections
docker-compose exec db psql -U postgres -d lesgo_db -c "SELECT count(*) FROM pg_stat_activity;"
```

---

## Monitoring Setup

### 1. Application Monitoring
- Set up error tracking (Sentry, Bugsnag)
- Configure uptime monitoring (UptimeRobot, Pingdom)
- Set up performance monitoring (New Relic, DataDog)

### 2. Server Monitoring
- CPU usage
- Memory usage
- Disk space
- Network traffic

### 3. Database Monitoring
- Connection pool usage
- Query performance
- Slow query log
- Database size

### 4. Log Monitoring
- Centralized logging (ELK Stack, Papertrail)
- Error rate tracking
- Security event monitoring
- Failed login attempts

---

## Backup Strategy

### 1. Database Backups
```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose exec -T db pg_dump -U postgres lesgo_db > backup_$DATE.sql
# Upload to S3 or backup server
```

### 2. Application Backups
- Code: Git repository
- Uploads: S3 or similar
- Configuration: Encrypted backup

### 3. Backup Schedule
- Database: Daily
- Files: Daily
- Configuration: On change
- Retention: 30 days

---

## Rollback Plan

### Docker Deployment
```bash
# Stop current containers
docker-compose down

# Checkout previous version
git checkout <previous-tag>

# Rebuild and start
docker-compose build
docker-compose up -d

# Restore database if needed
docker-compose exec -T db psql -U postgres lesgo_db < backup.sql
```

### Manual Deployment
```bash
# Checkout previous version
git checkout <previous-tag>

# Install dependencies
composer install --no-dev --optimize-autoloader

# Rollback migrations if needed
php artisan migrate:rollback

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Recache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Security Hardening

### 1. Server Level
- [ ] Firewall configured (UFW/iptables)
- [ ] SSH key-only authentication
- [ ] Fail2ban installed
- [ ] Automatic security updates enabled
- [ ] Non-root user for application

### 2. Application Level
- [x] APP_DEBUG=false in production
- [x] Strong database passwords
- [x] Redis password set
- [x] CORS properly configured
- [x] Rate limiting enabled
- [x] Security headers configured

### 3. Database Level
- [ ] Database user has minimal permissions
- [ ] Database not exposed to internet
- [ ] Regular backups configured
- [ ] Encryption at rest enabled

### 4. Network Level
- [ ] HTTPS enforced
- [ ] SSL certificate valid
- [ ] DDoS protection (Cloudflare)
- [ ] Load balancer configured (if needed)

---

## Performance Optimization

### 1. Application
```bash
# Enable OPcache
php artisan optimize

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue jobs
php artisan queue:work
```

### 2. Database
- Create indexes on frequently queried columns
- Use database query caching
- Optimize slow queries
- Use read replicas for scaling

### 3. Caching
- Redis for session storage
- Redis for cache storage
- API response caching
- Database query caching

### 4. CDN
- Serve static assets via CDN
- Use CDN for API if global audience

---

## Maintenance

### Regular Tasks
- [ ] Weekly: Review logs for errors
- [ ] Weekly: Check disk space
- [ ] Weekly: Review security events
- [ ] Monthly: Update dependencies
- [ ] Monthly: Review performance metrics
- [ ] Quarterly: Security audit
- [ ] Quarterly: Load testing

### Update Process
```bash
# 1. Backup
./backup.sh

# 2. Update code
git pull origin main

# 3. Update dependencies
composer install --no-dev --optimize-autoloader

# 4. Run migrations
php artisan migrate --force

# 5. Clear and recache
php artisan optimize:clear
php artisan optimize

# 6. Restart services
docker-compose restart  # Docker
# or
sudo systemctl restart php8.4-fpm  # Manual
```

---

## Support Contacts

### Technical Issues
- Backend Team: backend@example.com
- DevOps Team: devops@example.com

### Emergency Contacts
- On-call Engineer: +1-XXX-XXX-XXXX
- Team Lead: +1-XXX-XXX-XXXX

---

## Deployment Sign-off

- [ ] All pre-deployment checks completed
- [ ] Deployment executed successfully
- [ ] Post-deployment verification passed
- [ ] Monitoring configured
- [ ] Backups configured
- [ ] Team notified
- [ ] Documentation updated

**Deployed by:** _______________  
**Date:** _______________  
**Version:** _______________  
**Sign-off:** _______________

---

**Status:** Ready for production deployment ✅
