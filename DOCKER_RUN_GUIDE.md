# 🐳 Docker Build & Run Guide

**Status:** Building...  
**Time:** 5-10 minutes  
**Output:** Production Docker image (beszed:latest)  

---

## What's Happening

### Docker Build Process

```
Stage 1: Node.js Frontend Builder
├─ Base: node:20-alpine
├─ Install npm dependencies
├─ Run: npm run build
├─ Output: public/build/ folder
└─ ~3 minutes

Stage 2: PHP Production Image
├─ Base: php:8.2-fpm-alpine (small, fast)
├─ Install system packages
├─ Install PHP extensions (PDO, Bcmath, etc.)
├─ Install Composer
├─ Copy built frontend from Stage 1
├─ Install PHP dependencies
├─ Configure Nginx
├─ Configure Supervisor
├─ Setup entrypoint
└─ ~5-7 minutes

Total Build Time: 8-10 minutes
Final Image Size: ~500MB (optimized)
```

---

## After Build Completes

### Check Build Status
```bash
# List images
docker images | grep beszed

# Should show:
# REPOSITORY    TAG       IMAGE ID        CREATED         SIZE
# beszed        latest    abc123def456    2 minutes ago    500MB
```

### Run the Container
```bash
# Simple run
docker run -p 8080:8000 beszed:latest

# Or use the script
bash docker-run.sh

# With all environment variables
docker run -d \
  --name beszed-prod \
  -p 8080:8000 \
  -e APP_ENV="production" \
  -e APP_DEBUG="false" \
  -e DB_HOST="host.docker.internal" \
  -e DB_PORT="3307" \
  -e DB_DATABASE="beszed" \
  -e DB_USERNAME="root" \
  -e DB_PASSWORD="secret" \
  -e REDIS_HOST="host.docker.internal" \
  -e REDIS_PORT="6379" \
  beszed:latest
```

### Access Your App
```
🌐 Browser:  http://localhost:8080
📊 Status:   docker logs -f beszed-prod
🛑 Stop:     docker stop beszed-prod
🗑️  Remove:   docker rm beszed-prod
```

---

## Container Architecture

```
Inside Container (Supervisor manages):
├─ PHP-FPM (9000)
│  └─ Laravel application (15,000+ lines)
│
├─ Nginx (8000)
│  ├─ Reverse proxy to PHP-FPM
│  ├─ Serves static assets (public/build/)
│  ├─ Gzip compression enabled
│  └─ Security headers configured
│
└─ Queue Worker
   └─ Background jobs (if any)

External Connections:
├─ Database:  host.docker.internal:3307 (MySQL on host)
└─ Cache:     host.docker.internal:6379 (Redis on host)
```

---

## Container Environment

### Automatically Set
```bash
APP_NAME=Beszéd
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080
```

### Connect to Host Database
```bash
DB_HOST=host.docker.internal    # Docker's way to reach host
DB_PORT=3307                      # MySQL (still running locally)
DB_DATABASE=beszed
DB_USERNAME=root
DB_PASSWORD=secret
```

### Connect to Host Redis
```bash
CACHE_DRIVER=redis
REDIS_HOST=host.docker.internal
REDIS_PORT=6379
```

---

## What's Inside the Container

### Built into Image
```
✅ All PHP code
✅ All Vue.js components (compiled)
✅ 13 games (bundled)
✅ 1000+ stickers (assets included)
✅ Csillám animations (SVG included)
✅ Database migrations
✅ Laravel configuration
✅ Nginx configuration
✅ All dependencies (composer.lock)
✅ Production optimizations
```

### Running in Container
```
✅ PHP-FPM (serves app)
✅ Nginx (routes requests)
✅ Supervisor (manages processes)
✅ Logs (to stdout)
```

### Connecting to (on Host)
```
✅ MySQL database (port 3307)
✅ Redis cache (port 6379)
```

---

## Testing the Container

### After It Starts (localhost:8080)

#### 1. Homepage
```
curl http://localhost:8080
# Should return HTML homepage
```

#### 2. API Health Check
```
curl http://localhost:8080/api/health
# Should return: {"status": "ok"}
```

#### 3. View Logs
```
docker logs beszed-prod
# Shows all app logs in real-time
```

#### 4. Interactive Shell
```
docker exec -it beszed-prod bash
# Get shell inside container
# Run: php artisan tinker
# Run: php artisan migrate
```

#### 5. Browser Testing
```
Open: http://localhost:8080
- See homepage
- Click "Play Game"
- See games load
- See Csillám animate
- Click stickers
- Switch language
- Test speech (if enabled)
```

---

## Common Issues & Fixes

### Container won't start
```bash
# Check logs
docker logs beszed-prod

# Common causes:
# 1. Port 8080 already in use
docker run -p 8081:8000 beszed:latest  # Use different port

# 2. Database not accessible
# Make sure: docker-compose is still running
docker-compose -f docker-compose.dev.yml ps
```

### Port already in use
```bash
# Find process using port 8080
lsof -i :8080

# Or use different port
docker run -p 8081:8000 beszed:latest
```

### Can't connect to database
```bash
# Inside container, test connection
docker exec beszed-prod mysql -h host.docker.internal -u root -psecret -e "SELECT 1"

# Make sure docker-compose is still running
docker-compose -f docker-compose.dev.yml ps
```

### Image too large
```bash
# Normal - includes all assets, games, stickers
# To reduce: remove unused assets before building

# Check size
docker images beszed:latest
# Should be ~500MB
```

---

## Container Lifecycle

### Start Container
```bash
# Foreground (see logs directly)
docker run -p 8080:8000 beszed:latest

# Background (daemonized)
docker run -d -p 8080:8000 --name beszed-prod beszed:latest
```

### View Logs
```bash
# Live logs
docker logs -f beszed-prod

# Last 100 lines
docker logs --tail 100 beszed-prod

# With timestamps
docker logs -f --timestamps beszed-prod
```

### Stop Container
```bash
# Graceful stop
docker stop beszed-prod

# Forced stop
docker kill beszed-prod
```

### Remove Container
```bash
# Remove stopped container
docker rm beszed-prod

# Remove and its volumes (careful!)
docker rm -v beszed-prod
```

### Restart Container
```bash
docker restart beszed-prod
```

---

## Production Deployment

### Push to Docker Hub
```bash
# Login
docker login

# Tag image
docker tag beszed:latest yourusername/beszed:latest

# Push
docker push yourusername/beszed:latest

# Then on production server:
docker run -d -p 8000:8000 yourusername/beszed:latest
```

### Using Docker Compose (Production)
```yaml
version: '3.8'
services:
  app:
    image: yourusername/beszed:latest
    ports:
      - "8000:8000"
    environment:
      APP_ENV: production
      DB_HOST: db.example.com
      DB_DATABASE: beszed_prod
    # ... other config
```

---

## Monitoring Container

### CPU & Memory Usage
```bash
docker stats beszed-prod
# Shows real-time resource usage
```

### Inspect Container
```bash
docker inspect beszed-prod
# Shows detailed container configuration
```

### Container Processes
```bash
docker top beszed-prod
# Shows running processes inside container
```

---

## Scaling

### Multiple Containers (Load Balancing)
```bash
# Run 3 instances
for i in {1..3}; do
  docker run -d -p 808$i:8000 \
    --name beszed-$i \
    --link mysql:db \
    --link redis:cache \
    beszed:latest
done

# Use Nginx as load balancer
# Point to: localhost:8081, localhost:8082, localhost:8083
```

### With Docker Compose (Multiple Replicas)
```yaml
services:
  app:
    image: beszed:latest
    deploy:
      replicas: 3
    ports:
      - "8080-8082:8000"
```

---

## Quick Reference

### Check if Image Built
```bash
docker images beszed:latest
```

### Run Container
```bash
docker run -p 8080:8000 beszed:latest
```

### Stop Container
```bash
docker stop beszed-prod
```

### View Logs
```bash
docker logs -f beszed-prod
```

### Enter Container
```bash
docker exec -it beszed-prod bash
```

### Remove Container
```bash
docker rm beszed-prod
```

### Remove Image
```bash
docker rmi beszed:latest
```

---

## Status Checklist

```
AFTER BUILD COMPLETES:
☐ Image created (docker images shows beszed:latest)
☐ Container starts (docker run succeeds)
☐ Accessible at http://localhost:8080
☐ Database connected (migrations ready)
☐ Redis connected (cache working)
☐ Homepage loads
☐ Games playable
☐ Stickers visible
☐ Csillám animated
☐ Language switching works
```

---

## Timeline

```
Now:        Docker build started
+5 min:     Node dependencies installed
+8 min:     Frontend built
+3 min:     PHP image built
~10 min:    Image ready (beszed:latest)
+1 min:     Container started
           Access at http://localhost:8080 ✅
```

---

## Next Steps

1. **Wait for build to complete** (~10 minutes)
2. **Run container:** `docker run -p 8080:8000 beszed:latest`
3. **Test locally:** http://localhost:8080
4. **Push to Docker Hub** (optional)
5. **Deploy to production** (Railway, DigitalOcean, etc.)

---

**The Docker image will be production-ready once built!** 🚀
