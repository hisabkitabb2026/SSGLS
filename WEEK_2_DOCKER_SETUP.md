# Week 2: Docker Setup - Redis Cache Integration

This guide provides step-by-step instructions to integrate Redis cache service into your InvoiceShelf Docker environment.

---

## Step 1: Stop Current Docker Containers

Before making changes to docker-compose.yml, stop all running containers gracefully.

```bash
docker-compose down
```

**Expected Output:**
```
Stopping invoiceshelf-phpmyadmin ... done
Stopping invoiceshelf-nginx       ... done
Stopping invoiceshelf-app         ... done
Stopping invoiceshelf-mysql       ... done
Removing invoiceshelf-phpmyadmin ... done
Removing invoiceshelf-nginx       ... done
Removing invoiceshelf-app         ... done
Removing invoiceshelf-mysql       ... done
Removing network invoiceshelf-network
```

**Verification:**
```bash
docker ps -a
```
You should see no containers with `invoiceshelf` prefix in the running state.

---

## Step 2: Add Redis Service to docker-compose.yml

Add the Redis service to your existing `docker-compose.yml` file. Copy the following snippet and paste it **after the MySQL service and before the phpMyAdmin service** (around line 60):

```yaml
  # Redis Cache Service
  redis:
    image: redis:7-alpine
    container_name: invoiceshelf-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass redis_secret_password
    volumes:
      - redis-data:/data
    networks:
      - invoiceshelf-network
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "--raw", "incr", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5
```

**Update Environment Variables:**

Update the `app` service's environment variables to include Redis configuration. Add these lines to the `environment` section:

```yaml
      - CACHE_DRIVER=redis
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - REDIS_PASSWORD=redis_secret_password
```

**Add Volume:**

Add `redis-data` to the volumes section at the bottom of the file:

```yaml
volumes:
  mysql-data:
  redis-data:
```

**Complete Updated Sections:**

Your `docker-compose.yml` should look like this after modifications:

```yaml
version: '3.8'

services:
  # InvoiceShelf Application
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: invoiceshelf-app:latest
    container_name: invoiceshelf-app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
      - ./storage:/var/www/html/storage
    networks:
      - invoiceshelf-network
    depends_on:
      - mysql
      - redis
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=invoiceshelf
      - DB_USERNAME=root
      - DB_PASSWORD=secret123
      - CACHE_DRIVER=redis
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - REDIS_PASSWORD=redis_secret_password
    ports:
      - "8080:9000"

  # Nginx Web Server
  nginx:
    image: nginx:alpine
    container_name: invoiceshelf-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - invoiceshelf-network
    depends_on:
      - app

  # MySQL Database
  mysql:
    image: mysql:8.0
    container_name: invoiceshelf-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: invoiceshelf
      MYSQL_ROOT_PASSWORD: secret123
      MYSQL_ROOT_HOST: '%'
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - invoiceshelf-network
    ports:
      - "3306:3306"

  # Redis Cache Service
  redis:
    image: redis:7-alpine
    container_name: invoiceshelf-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass redis_secret_password
    volumes:
      - redis-data:/data
    networks:
      - invoiceshelf-network
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "--raw", "incr", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5

  # phpMyAdmin (Database Management)
  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: invoiceshelf-phpmyadmin
    restart: unless-stopped
    environment:
      PMA_HOST: mysql
      PMA_PORT: 3306
      MYSQL_ROOT_PASSWORD: secret123
    networks:
      - invoiceshelf-network
    ports:
      - "8081:80"

networks:
  invoiceshelf-network:
    driver: bridge

volumes:
  mysql-data:
  redis-data:
```

---

## Step 3: Start Redis Service

Once you've updated the `docker-compose.yml` file, start all services with Redis included.

```bash
docker-compose up -d
```

**Expected Output:**
```
Creating invoiceshelf-mysql      ... done
Creating invoiceshelf-redis      ... done
Creating invoiceshelf-app        ... done
Creating invoiceshelf-nginx      ... done
Creating invoiceshelf-phpmyadmin ... done
```

**Verify all services are running:**
```bash
docker-compose ps
```

You should see all services in `Up` state:
```
NAME                    STATUS
invoiceshelf-app        Up (healthy)
invoiceshelf-mysql      Up (healthy)
invoiceshelf-redis      Up (healthy)
invoiceshelf-nginx      Up
invoiceshelf-phpmyadmin Up
```

---

## Step 4: Verify Redis is Running

Connect to the Redis service and verify it's working correctly.

**Option A: Using redis-cli (Docker)**
```bash
docker exec -it invoiceshelf-redis redis-cli -a redis_secret_password
```

**Interactive commands to test:**
```
ping
# Expected: PONG

set test-key "Hello Redis"
# Expected: OK

get test-key
# Expected: "Hello Redis"

exit
```

**Option B: Using redis-cli (Host machine - if installed)**
```bash
redis-cli -h 127.0.0.1 -p 6379 -a redis_secret_password ping
```

**Expected Output:**
```
PONG
```

**Check Redis logs:**
```bash
docker logs invoiceshelf-redis
```

You should see:
```
* Ready to accept connections
```

---

## Step 5: Reset Cache in Laravel Application

Clear the Laravel application cache to ensure it uses Redis properly.

**Inside Docker container:**
```bash
docker exec -it invoiceshelf-app php artisan cache:clear
```

**Expected Output:**
```
Application cache cleared
```

**Optional - Clear all caches:**
```bash
docker exec -it invoiceshelf-app php artisan cache:flush
docker exec -it invoiceshelf-app php artisan config:clear
docker exec -it invoiceshelf-app php artisan view:clear
```

---

## Step 6: Test Health Endpoint

Test the application's health endpoint to verify everything is working correctly.

**Using curl:**
```bash
curl http://localhost:80/api/health
```

**Expected Output (successful):**
```json
{
  "status": "ok",
  "timestamp": "2026-08-30T10:30:45Z",
  "services": {
    "database": "connected",
    "cache": "connected",
    "redis": "connected"
  }
}
```

**Using curl with verbose output (if needed):**
```bash
curl -v http://localhost:80/api/health
```

**Inside container with Laravel Artisan:**
```bash
docker exec -it invoiceshelf-app php artisan tinker
```

Then in Tinker:
```php
Cache::put('test-key', 'Hello Redis', 60);
Cache::get('test-key');
// Should return: "Hello Redis"
```

---

## Troubleshooting

### Redis container fails to start
```bash
docker logs invoiceshelf-redis
```
Check for:
- Port 6379 already in use
- Invalid docker-compose.yml syntax
- Volume permission issues

### Redis connection refused
```bash
docker exec -it invoiceshelf-redis redis-cli ping
```
- Verify password is correct: `redis_secret_password`
- Check REDIS_HOST and REDIS_PORT environment variables in app service
- Verify network connectivity between containers

### Cache not persisting
```bash
docker volume ls | grep redis
```
Ensure `redis-data` volume exists and is mounted at `/data` in the Redis container.

### View Redis memory usage
```bash
docker exec -it invoiceshelf-redis redis-cli -a redis_secret_password info stats
```

---

## Verification Checklist

- [ ] All Docker containers stopped with `docker-compose down`
- [ ] Redis service added to `docker-compose.yml`
- [ ] App service updated with Redis environment variables
- [ ] `redis-data` volume added to docker-compose.yml
- [ ] All services started with `docker-compose up -d`
- [ ] All containers show `Up` status in `docker-compose ps`
- [ ] Redis responds to `ping` command
- [ ] Laravel cache cleared with `php artisan cache:clear`
- [ ] Health endpoint returns successful response
- [ ] Cache operations work in Laravel Tinker

---

## Next Steps

1. **Update .env file (if not in Docker environment):**
   ```
   CACHE_DRIVER=redis
   REDIS_HOST=redis
   REDIS_PORT=6379
   REDIS_PASSWORD=redis_secret_password
   ```

2. **Monitor Redis performance:**
   ```bash
   docker exec -it invoiceshelf-redis redis-cli -a redis_secret_password monitor
   ```

3. **Backup Redis data:**
   ```bash
   docker exec -it invoiceshelf-redis redis-cli -a redis_secret_password BGSAVE
   ```

4. **Scale up for production:**
   - Change `redis_secret_password` to a strong password
   - Add Redis persistence configuration in production
   - Set up Redis sentinel for high availability
   - Monitor Redis memory usage and set maxmemory policies

---

## Quick Reference Commands

```bash
# View all containers
docker-compose ps

# View logs
docker logs invoiceshelf-redis
docker logs invoiceshelf-app

# Connect to Redis CLI
docker exec -it invoiceshelf-redis redis-cli -a redis_secret_password

# Clear cache
docker exec -it invoiceshelf-app php artisan cache:clear

# Restart services
docker-compose restart

# Stop all services
docker-compose down

# Remove all data and restart
docker-compose down -v
docker-compose up -d
```

---

**Last Updated:** 2026-08-30
**Version:** 1.0
