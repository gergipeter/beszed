# 🚀 Cloudflare Deployment Guide

**Setup Time:** 30 minutes  
**Cost:** €0-20/month (depends on traffic)  
**Features:** Global CDN, DDoS protection, WAF, Workers edge computing  

---

## Option 1: Cloudflare Pages (Static/Frontend Only)

### 1.1 Deploy Frontend to Cloudflare Pages
```bash
# Install Wrangler CLI
npm install -g @cloudflare/wrangler

# Login to Cloudflare
wrangler login

# Build frontend
npm run build

# Deploy
wrangler pages deploy dist --project-name=beszed
```

### 1.2 Connect Domain
```
1. Go to Cloudflare dashboard
2. Add your domain
3. Update nameservers at registrar
4. Wait 24-48 hours for propagation
5. Pages automatically gets HTTPS & CDN
```

---

## Option 2: Cloudflare Workers + API (Recommended)

### 2.1 Create Cloudflare Account
```bash
1. Go to cloudflare.com
2. Sign up (free tier available)
3. Add domain
4. Update nameservers
```

### 2.2 Deploy Worker
```bash
# Create worker project
wrangler init beszed-worker

# Copy worker code
cp cloudflare-worker.js src/index.js

# Configure wrangler.toml (already created)
# Update: account_id, zone_id, API_ORIGIN

# Deploy
wrangler publish
```

### 2.3 Setup Environment Variables
```bash
# In wrangler.toml:
[env.production.vars]
API_ORIGIN = "https://api.your-backend.com"
LOG_LEVEL = "info"
CACHE_TTL = "3600"

# Deploy with env
wrangler publish --env production
```

### 2.4 Configure D1 Database (Optional - Edge SQL)
```bash
# Create database
wrangler d1 create beszed_db

# Binding added to wrangler.toml automatically

# Deploy migrations
wrangler d1 execute beszed_db --file=./database.sql --remote
```

### 2.5 Setup KV Storage (Optional - Edge Cache)
```bash
# Create KV namespace
wrangler kv:namespace create CACHE

# Use in worker:
# await env.CACHE.get('key')
# await env.CACHE.put('key', value)
```

---

## Option 3: Docker + Railway (Alternative)

### 3.1 Deploy Docker to Railway
```bash
# Login to Railway
railway login

# Link to project
railway link

# Deploy
railway up

# Get public URL
railway open
```

### 3.2 Add Custom Domain
```
1. Railway dashboard → Settings
2. Add domain: api.yourdomain.com
3. Point DNS CNAME to Railway endpoint
```

---

## Docker Image - Localhost Development

### 4.1 Build & Run Locally
```bash
# Build with docker-compose
docker-compose -f docker-compose.dev.yml up -d

# Access locally:
# Web:      http://localhost
# API:      http://localhost:8000/api
# MySQL:    localhost:3306
# Redis:    localhost:6379
```

### 4.2 First Run Setup
```bash
# Connect to app container
docker exec -it beszed-app bash

# Run migrations
php artisan migrate:fresh --seed

# Generate test data
php artisan db:seed

# Exit
exit
```

### 4.3 View Logs
```bash
# All services
docker-compose -f docker-compose.dev.yml logs -f

# Specific service
docker-compose -f docker-compose.dev.yml logs -f app
docker-compose -f docker-compose.dev.yml logs -f db
```

### 4.4 Stop Services
```bash
# Stop all
docker-compose -f docker-compose.dev.yml down

# Stop but keep volumes
docker-compose -f docker-compose.dev.yml down --volumes
```

---

## Cloudflare Configuration

### 5.1 Setup Domain
```
1. Cloudflare Dashboard
2. Add Site → yourdomain.com
3. Select Free plan (or Pro for $20/month)
4. Copy nameservers:
   - ns1.cloudflare.com
   - ns2.cloudflare.com
5. Update at registrar
6. Wait for activation (24-48h)
```

### 5.2 Configure DNS Records
```
Type    Name        Content              Proxy
------  ----------  -----------------    ------
CNAME   api         backend.railway.app  Proxied
CNAME   www         yourdomain.com       Proxied
A       @           1.2.3.4              Proxied
MX      @           mx.mailgun.org       DNS only
TXT     @           v=spf1 include:...   DNS only
```

### 5.3 Setup SSL/TLS
```
1. Cloudflare → SSL/TLS
2. Encryption level: Full (Strict)
3. Origin certificate: (auto-generated)
4. Always HTTPS: On
5. HSTS: Max age 12 months
6. Minimum TLS Version: 1.2
```

### 5.4 Enable Security
```
1. Security → Firewall Rules
2. Add rule: Block known bots
3. Add rule: Rate limiting (10 req/sec)
4. WAF: Enable managed rules
5. Bot Management: Challenge bots
6. DDoS: Always On
```

### 5.5 Performance
```
1. Speed → Optimization
2. Minify: CSS, JavaScript, HTML
3. Rocket Loader: Off (JavaScript incompatible)
4. Browser Cache TTL: 1 month
5. Cache Level: Cache Everything
6. Prefetch Preload: On
```

---

## Localhost Docker Workflow

### Quick Start (One Command)
```bash
# Build everything and start
docker-compose -f docker-compose.dev.yml up -d --build

# Wait for services to be healthy
docker-compose -f docker-compose.dev.yml ps

# Access
# Browser:  http://localhost
# Terminal: docker-compose -f docker-compose.dev.yml exec app bash
```

### Development Loop
```bash
# Make code changes (auto-reload)
# - Backend: Changes trigger Laravel reload
# - Frontend: npm run dev watches for changes
# - Database: docker-compose handles persistence

# Run tests
docker exec -it beszed-app php artisan test

# Check logs
docker-compose -f docker-compose.dev.yml logs -f app

# Access DB
docker-compose -f docker-compose.dev.yml exec db mysql -u root -psecret beszed

# Access Redis
docker-compose -f docker-compose.dev.yml exec redis redis-cli
```

### Cleanup
```bash
# Remove stopped containers
docker-compose -f docker-compose.dev.yml down -v

# Remove all images
docker rmi beszed:latest

# Clean system
docker system prune -a
```

---

## Production Deployment Path

### Option A: Cloudflare → Railway Backend
```
┌─────────────────────────────────┐
│    Users' Browser              │
└────────────┬────────────────────┘
             │ HTTPS
             ▼
┌─────────────────────────────────┐
│    Cloudflare CDN/Workers       │
│ • Edge caching                  │
│ • DDoS protection               │
│ • WAF                           │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│    Railway Backend              │
│ • Docker app                    │
│ • PostgreSQL                    │
│ • Redis cache                   │
└─────────────────────────────────┘

Cost: $5-50/month + Cloudflare free
Scaling: Auto (Railway handles it)
Performance: Excellent (edge caching)
```

### Option B: Cloudflare Pages + API Workers
```
┌─────────────────────────────────┐
│    Users' Browser              │
└────────────┬────────────────────┘
             │
     ┌───────┴────────┐
     ▼                ▼
┌──────────┐    ┌──────────────────────┐
│ Pages    │    │ Workers              │
│(frontend)│    │ (API + auth + cache) │
│ Static   │    │ Global edge nodes    │
└──────────┘    └──────────┬───────────┘
                           │
                           ▼
                ┌──────────────────────┐
                │ Backend (optional)   │
                │ • D1 Database        │
                │ • KV Storage         │
                │ • Webhooks           │
                └──────────────────────┘

Cost: Free tier → $20/month Pro
Scaling: Unlimited (Cloudflare edge)
Performance: Outstanding (edge compute)
```

---

## Cost Comparison

```
Option 1: Localhost Only
  Cost: $0
  Performance: Great (local)
  Availability: Your machine only
  Scaling: Not applicable

Option 2: Cloudflare + Railway
  Base: €0 (Cloudflare free) + €5 (Railway)
  With growth: €20-100+/month
  Performance: Excellent global
  Availability: 99.9%+ uptime
  Scaling: Auto

Option 3: Cloudflare Pages + Workers
  Base: €0 (free tier)
  Pro plan: €20/month
  Performance: Outstanding
  Availability: 99.99%+
  Scaling: Unlimited
```

---

## Deployment Status

```
✅ Docker image:           READY (see Dockerfile)
✅ Localhost setup:        READY (docker-compose.dev.yml)
✅ Cloudflare config:      READY (wrangler.toml)
✅ Worker code:            READY (cloudflare-worker.js)
✅ Nginx reverse proxy:    READY (nginx.conf)

🚀 Ready to deploy!
```

---

## Next Steps

1. **Choose deployment:**
   - [ ] Option A: Localhost only (dev)
   - [ ] Option B: Cloudflare + Railway (production)
   - [ ] Option C: Cloudflare Pages + Workers (edge)

2. **Setup locally:**
   - [ ] `docker-compose -f docker-compose.dev.yml up -d`
   - [ ] Test at `http://localhost`

3. **Deploy to cloud:**
   - [ ] Setup Cloudflare domain
   - [ ] Deploy backend to Railway/Docker
   - [ ] Point DNS to Cloudflare
   - [ ] Enable SSL/TLS

4. **Monitor:**
   - [ ] Setup error tracking (Sentry)
   - [ ] Enable analytics
   - [ ] Watch Cloudflare dashboard

---

**Status: ✅ READY FOR DEPLOYMENT**

Choose your path and deploy! 🚀
