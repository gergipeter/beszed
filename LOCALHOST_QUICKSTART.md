# 🎯 Localhost Quickstart - 5 Minutes to Live

**Time:** 5 minutes  
**Prerequisites:** Docker & Docker Compose installed  

---

## One-Command Start

```bash
cd D:\beszed
docker-compose -f docker-compose.dev.yml up -d --build
```

That's it! ✅

---

## Access Points

| Service | URL | Username | Password |
|---------|-----|----------|----------|
| **Web App** | http://localhost | - | - |
| **API** | http://localhost:8000/api | - | - |
| **MySQL** | localhost:3306 | root | secret |
| **Redis** | localhost:6379 | - | - |

---

## What's Running

```
✅ app     → Laravel API (Port 8000)
✅ db      → MySQL 8.0 (Port 3306)
✅ redis   → Cache (Port 6379)
✅ nginx   → Reverse proxy (Port 80)
```

---

## First Run Setup (Auto-runs)

1. Installs PHP & Node dependencies
2. Runs database migrations
3. Seeds sample data
4. Starts development servers

Check progress:
```bash
docker-compose -f docker-compose.dev.yml logs -f app
```

---

## Common Commands

### View Logs
```bash
# All services
docker-compose -f docker-compose.dev.yml logs -f

# Specific service
docker-compose -f docker-compose.dev.yml logs -f app
docker-compose -f docker-compose.dev.yml logs -f db
```

### Access Shell
```bash
# PHP shell
docker exec -it beszed-app bash

# MySQL shell
docker-compose -f docker-compose.dev.yml exec db mysql -u root -psecret beszed

# Redis shell
docker-compose -f docker-compose.dev.yml exec redis redis-cli
```

### Run Commands
```bash
# Laravel commands
docker exec -it beszed-app php artisan migrate
docker exec -it beszed-app php artisan tinker
docker exec -it beszed-app php artisan test

# Node commands
docker exec -it beszed-app npm run build
```

### Stop Everything
```bash
docker-compose -f docker-compose.dev.yml down
```

### Reset Database
```bash
docker-compose -f docker-compose.dev.yml down -v
docker-compose -f docker-compose.dev.yml up -d
```

---

## Verify It's Working

```bash
# Check all services are healthy
docker-compose -f docker-compose.dev.yml ps

# Should show:
# NAME         STATUS      PORTS
# beszed-app   Up (healthy)
# beszed-db    Up (healthy)
# beszed-redis Up (healthy)
# beszed-nginx Up
```

Test API:
```bash
# Should return 200 OK
curl http://localhost:8000/api/health

# Or open in browser
http://localhost:8000
```

---

## Troubleshooting

### Port already in use
```bash
# Free up port (e.g., 8000)
netstat -ano | findstr :8000
taskkill /PID <PID> /F
```

### Database won't start
```bash
# Check MySQL logs
docker-compose -f docker-compose.dev.yml logs db

# Restart DB
docker-compose -f docker-compose.dev.yml restart db
```

### Out of disk space
```bash
# Clean up Docker
docker system prune -a
docker volume prune
```

### Want fresh start
```bash
# Remove everything and rebuild
docker-compose -f docker-compose.dev.yml down -v
docker rmi beszed:latest
docker-compose -f docker-compose.dev.yml up -d --build
```

---

## Next Steps

1. **Explore the app:**
   - Visit http://localhost
   - Test games
   - Check API at http://localhost:8000/api

2. **Develop locally:**
   - Edit files, they auto-reload
   - Run tests: `docker exec -it beszed-app php artisan test`
   - Check logs: `docker-compose -f docker-compose.dev.yml logs -f`

3. **Deploy to cloud:**
   - See CLOUDFLARE_DEPLOYMENT.md
   - Or DEPLOYMENT_GUIDE_DOCKER.md for DigitalOcean

---

**🚀 You're live locally!**

Questions? See:
- DEVELOPER_ONBOARDING.md
- ARCHITECTURE.md
- API_DOCUMENTATION.md
