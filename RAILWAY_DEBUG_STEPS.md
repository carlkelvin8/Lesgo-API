# Railway Deployment Debug Steps

Your environment variables look perfect now, but you're still getting a 502 error. Here's how to debug this step by step:

## Step 1: Check Railway Deployment Logs

1. Go to Railway Dashboard → Your Project → Deployments
2. Click on the latest deployment
3. Look at both **Build Logs** and **Deploy Logs**
4. Look for specific error messages

**Common errors to look for:**
- `ERROR: APP_KEY is not set!` (should be fixed now)
- `ERROR: DB_HOST is not set!` (should be fixed now)
- `Migration failed`
- `Laravel bootstrap failed`
- `Nginx configuration is invalid`
- `Cannot connect to database`

## Step 2: Test Health Check Locally

Run this locally to make sure everything works:

```bash
# Test the health check script
php health-check.php

# Test Laravel locally
php artisan config:show app.key
php artisan route:list --path=api/v1/ping
php artisan migrate:status
```

## Step 3: Railway Service Configuration

Check these in Railway:

### Plugins Required:
- ✅ PostgreSQL plugin (should auto-inject DB variables)
- ✅ Redis plugin (should auto-inject Redis variables)

### Port Configuration:
Railway should automatically set the `PORT` environment variable. Your app listens on this port.

### Domain Configuration:
Make sure you have a domain assigned in Railway → Settings → Domains

## Step 4: Alternative Dockerfile

If the current setup isn't working, try using the alternative Dockerfile:

1. Rename current Dockerfile: `mv Dockerfile Dockerfile.backup`
2. Use the new one: `mv Dockerfile.railway Dockerfile`
3. Push to trigger redeploy

## Step 5: Simplified Startup (Emergency Fix)

If all else fails, create this minimal startup script:

```bash
#!/bin/bash
echo "==> Emergency startup"
PORT=${PORT:-8080}

# Basic nginx config
echo "server { listen $PORT; root /var/www/html/public; index index.php; location / { try_files \$uri \$uri/ /index.php?\$query_string; } location ~ \.php$ { fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name; include fastcgi_params; } }" > /etc/nginx/sites-available/default

# Start services
php-fpm -D
nginx -g "daemon off;" &

# Keep container running
wait
```

## Step 6: Check Specific Issues

### Database Connection Issues:
```bash
# Test in Railway logs
php artisan db:show
php artisan migrate:status
```

### Redis Connection Issues:
```bash
# Test in Railway logs  
php artisan tinker
>>> Redis::ping()
```

### Laravel Configuration Issues:
```bash
# Test in Railway logs
php artisan config:show
php artisan route:list
```

## Step 7: What to Share for Further Debug

If still failing, share these from Railway logs:

1. **Build logs** (the Docker build process)
2. **Deploy logs** (the startup process)
3. **Any specific error messages**
4. **The exact point where it fails**

## Most Likely Issues (in order):

1. **Database migration failure** - PostgreSQL plugin not properly connected
2. **Laravel configuration cache issues** - Config cache conflicts
3. **Nginx configuration problems** - Port binding issues
4. **PHP extension missing** - Required extensions not installed
5. **File permissions** - Storage/cache directory permissions

## Quick Test Commands

Run these in Railway's deployment logs to debug:

```bash
# Check if PHP works
php --version

# Check if Laravel works
php artisan --version

# Check database
php artisan db:show

# Check routes
php artisan route:list --path=api/v1/ping

# Check config
php artisan config:show app.key
```

## Next Steps

1. **Check Railway logs first** - this will tell us exactly where it's failing
2. **Share the specific error message** - I can provide a targeted fix
3. **Try the alternative Dockerfile** if the current one has issues

The 502 error means the container is starting but crashing before it can serve requests. The logs will show us exactly why.