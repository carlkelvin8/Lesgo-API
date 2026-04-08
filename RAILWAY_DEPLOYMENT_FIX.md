# Railway Deployment Fix Guide

## Immediate Actions Required

### 1. Fix Environment Variables in Railway

Go to your Railway project → Variables tab and set these **critical** variables:

```env
# CRITICAL - Generate this first!
APP_KEY=base64:YOUR_GENERATED_KEY_HERE

# Update with your actual Railway domain
APP_URL=https://your-project-name.up.railway.app

# Ensure these are set (Railway should auto-inject from plugins)
DB_CONNECTION=pgsql
DB_HOST=${{Postgres.RAILWAY_PRIVATE_DOMAIN}}
DB_PORT=5432
DB_DATABASE=${{Postgres.POSTGRES_DB}}
DB_USERNAME=${{Postgres.POSTGRES_USER}}
DB_PASSWORD=${{Postgres.POSTGRES_PASSWORD}}

# Redis (from Railway Redis plugin)
REDIS_CLIENT=predis
REDIS_HOST=${{Redis.REDISHOST}}
REDIS_PORT=${{Redis.REDISPORT}}
REDIS_PASSWORD=${{Redis.REDISPASSWORD}}

# Production settings
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
LOG_LEVEL=error

# Cache/Queue/Session
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### 2. Generate APP_KEY

Run this locally to generate a key:
```bash
php artisan key:generate --show
```

Copy the output (including `base64:`) and set it as `APP_KEY` in Railway.

### 3. Verify Railway Plugins

Ensure you have these plugins added to your Railway project:
- **PostgreSQL** plugin
- **Redis** plugin

### 4. Check Domain Configuration

In Railway → Settings → Domains, ensure you have a domain assigned and update `APP_URL` accordingly.

## Common Error Scenarios

### Error: "Application failed to respond"

**Cause:** Usually one of these:
1. Missing or invalid `APP_KEY`
2. Database connection failure
3. PHP/dependency issues
4. Port binding problems

**Debug Steps:**
1. Check Railway logs: Project → Deployments → Click latest deployment → View logs
2. Look for specific error messages in the startup logs
3. Test the health endpoint: `https://your-domain.railway.app/api/v1/ping`

### Error: Database Connection Issues

**Symptoms:** Migration failures, database connection errors

**Fix:**
1. Verify PostgreSQL plugin is added
2. Check that Railway auto-injected the database variables
3. Ensure `DB_HOST` uses `${{Postgres.RAILWAY_PRIVATE_DOMAIN}}`

### Error: Redis Connection Issues

**Symptoms:** Cache/session/queue failures

**Fix:**
1. Verify Redis plugin is added
2. Check Redis variables are properly set
3. Ensure `REDIS_CLIENT=predis` (not `phpredis`)

## Deployment Verification

After fixing the above, verify your deployment:

1. **Health Check:**
   ```
   curl https://your-domain.railway.app/api/v1/ping
   ```

2. **Expected Response:**
   ```json
   {
     "message": "LeSGo API v1 OK",
     "timestamp": "2026-04-08T...",
     "environment": "production",
     "php_version": "8.2.x",
     "laravel_version": "12.x",
     "database": "connected",
     "redis": "connected"
   }
   ```

3. **Test API Endpoints:**
   ```bash
   # Test service listing (public endpoint)
   curl https://your-domain.railway.app/api/v1/services
   
   # Test registration (if you want to verify full functionality)
   curl -X POST https://your-domain.railway.app/api/v1/auth/register \
     -H "Content-Type: application/json" \
     -d '{"name":"Test User","email":"test@example.com","password":"password123","role":"customer"}'
   ```

## If Still Failing

1. **Check Railway Logs:**
   - Go to Railway → Your Service → Deployments
   - Click on the latest deployment
   - Check both build logs and runtime logs

2. **Common Log Errors to Look For:**
   - `APP_KEY` not set
   - Database connection refused
   - Redis connection failed
   - PHP fatal errors
   - Nginx configuration issues

3. **Enable Debug Mode Temporarily:**
   Set `APP_DEBUG=true` in Railway to see detailed error messages, then set back to `false` after debugging.

## Updated Files

The following files have been updated to improve Railway compatibility:
- `Dockerfile` - Changed from PHP 8.4 to 8.2
- `docker/start.sh` - Added environment variable validation
- `routes/api.php` - Enhanced ping endpoint with debugging info