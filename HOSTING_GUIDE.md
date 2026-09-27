# Beszéd - Hosting & Domain Guide

**Goal:** Cheapest reliable hosting for production launch  
**Budget:** $50-200/month for startup  
**Scaling:** Easy upgrades as you grow  

---

## 🎯 Quick Answer: Best Budget Option

### Recommended Setup (Best Value)
```
Total Cost: ~$40-60/month

├── Server (VPS): $15-20/month
│   └── DigitalOcean or Hetzner
├── Database (MySQL): $0 (included)
├── Redis (Cache): $0-5/month
├── Domain: $10/year
├── Email: $5-10/month (optional)
└── SSL Certificate: $0 (Let's Encrypt - free)
```

---

## 💰 Hosting Comparison

### Budget Tier ($40-60/month)

| Provider | Server | CPU | RAM | Storage | Price/mo | Best For |
|----------|--------|-----|-----|---------|----------|----------|
| **DigitalOcean** | Droplet | 1 | 1GB | 25GB | $6 | Beginners |
| **DigitalOcean** | Droplet | 2 | 2GB | 50GB | $12 | **Recommended** |
| **Hetzner** | Cloud | 2 | 2GB | 40GB | $4.50 | Budget |
| **Linode** | Nanode | 1 | 1GB | 25GB | $5 | Reliable |
| **Vultr** | Cloud | 1 | 1GB | 25GB | $5 | Global CDN |

**Add:** Database ($10-15) + Domain ($10/year) + Backups ($5)

### Mid Tier ($100-150/month)

| Use Case | Setup |
|----------|-------|
| 1000+ users | 2x 2GB VPS + managed DB |
| Multi-region | 1 main + 2 replica regions |
| High availability | Load balancer + 3 servers |

### Enterprise Tier ($500+/month)

| Use Case | Setup |
|----------|-------|
| 10K+ users | Kubernetes cluster |
| SLA 99.9% | Multi-region + redundancy |
| Dedicated support | Premium hosting provider |

---

## 🔧 RECOMMENDED: DigitalOcean Setup

### Why DigitalOcean?
✅ Simple to use  
✅ Great for Laravel  
✅ Cheap & reliable  
✅ Excellent documentation  
✅ App Platform (no DevOps needed)  
✅ 1-click deployments  

### Option 1: DigitalOcean App Platform (Easiest)

**Cost:** $12/month for app + $15/month for database = **$27/month**

**Setup (No Docker knowledge needed):**

1. **Create Account**
   ```
   https://www.digitalocean.com
   → Sign up (get $200 credit for 60 days)
   ```

2. **Deploy App**
   ```
   Dashboard → App Platform → Create App
   → Connect GitHub repo
   → Auto-deploy on push
   → Done! (no Docker needed)
   ```

3. **Database**
   ```
   Dashboard → Databases → Create
   → MySQL 8.0
   → Basic plan ($15/month)
   → Auto-backups included
   ```

4. **Domain**
   ```
   Dashboard → Networking → Domains
   → Connect your domain
   → Point DNS (free)
   ```

5. **SSL Certificate**
   ```
   Automatic with App Platform
   (Let's Encrypt, auto-renew)
   ```

**Total Setup Time:** 15 minutes  
**Monthly Cost:** $27  
**Scaling:** Easy (1-click upgrades)

### Option 2: DigitalOcean Droplet (More Control)

**Cost:** $12/month server + $15/month database = **$27/month**

**What You Get:**
- Full Ubuntu 22.04 Linux server
- 2 CPU cores
- 2GB RAM
- 50GB SSD storage
- Unlimited bandwidth

**Setup Steps:**

```bash
# 1. Create Droplet
Dashboard → Droplets → Create
  - Ubuntu 22.04 LTS
  - $12/month plan (2GB RAM)
  - NYC region (choose closest to you)

# 2. Connect via SSH
ssh root@your_droplet_ip

# 3. Setup environment
apt-get update && apt-get upgrade -y

# 4. Install Docker
curl https://get.docker.com -o get-docker.sh
sh get-docker.sh

# 5. Clone repo & deploy
git clone https://github.com/YOUR_REPO/beszed.git
cd beszed
docker-compose up -d

# 6. Setup domain
Point DNS to Droplet IP
Configure Let's Encrypt for HTTPS
```

**Time to Deploy:** 30 minutes  
**Complexity:** Medium

---

## 🌍 Alternative Providers (Cheap & Good)

### Hetzner (Cheapest)
```
Price: $4.50/month (2GB)
Location: Germany/Finland
Best for: EU market
Pros: Extremely cheap, good uptime
Cons: Less UI polish than DigitalOcean
```

**Setup:**
```bash
1. Create account: https://www.hetzner.cloud
2. Create server (2GB = €4.50/month)
3. Install Docker: same as above
4. Deploy Beszéd
5. Cost: ~€30/month all-in
```

### Linode (Reliable)
```
Price: $5/month (1GB) or $10/month (2GB)
Location: Multiple worldwide
Best for: Reliability focus
Pros: 24/7 support, long history
Cons: Slightly pricier than alternatives
```

### Render (Easiest Alternative)
```
Price: Free tier, then $7/month
Best for: Zero DevOps setup
Pros: 1-click deployment, auto-scaling
Cons: Limited free tier, pricier at scale
```

---

## 📊 Complete Cost Breakdown (First Year)

### Scenario: Launch with 100 users

| Item | Cost | Notes |
|------|------|-------|
| **Server** | $12/mo | DigitalOcean 2GB Droplet |
| **Database** | $15/mo | Managed MySQL |
| **Domain** | $12/year | .com domain (~$1/mo) |
| **Email** | $0 | Use Gmail (free) |
| **SSL** | $0 | Let's Encrypt (free) |
| **Backups** | $5/mo | DigitalOcean Backups |
| **CDN** | $0 | Cloudflare (free tier) |
| **Monitoring** | $0 | Built-in (free) |
| | | |
| **Monthly Total** | ~$32 | Server + DB + Backups |
| **Annual Total** | ~$396 | Including domain |

---

## 🚀 Step-by-Step: Launch on DigitalOcean (Recommended)

### Step 1: Get Domain ($10/year)

**Option A: GoDaddy**
```
1. Go to: godaddy.com
2. Search for domain (e.g., beszed-therapy.com)
3. Buy: ~$10-15/year
4. Add to cart & checkout
```

**Option B: Namecheap**
```
1. Go to: namecheap.com
2. Search for domain
3. Buy: ~$5-10/year (cheaper than GoDaddy)
4. More popular with developers
```

**Recommended:** Namecheap (cheaper + developer-friendly)

### Step 2: Get Hosting ($27/month)

**Using DigitalOcean App Platform (Easiest):**

```bash
1. Sign up: https://digitalocean.com
   → Use promo code for $200 credit

2. Create App:
   Dashboard → App Platform → Create App
   → Select your GitHub repo
   → Choose branch: main
   → Set environment variables:
      DB_HOST=db-host.internal
      DB_DATABASE=beszed
      DB_USERNAME=user
      OPENAI_API_KEY=sk-...
   → Deploy (takes 5 min)

3. Create Database:
   Dashboard → Databases → Create
   → MySQL 8.0
   → Choose size: $15/month
   → Same datacenter as app
   → Enable automated backups
   → Copy connection string

4. Connect Domain:
   Dashboard → App Platform → [Your App]
   → Settings → Domains
   → Add your domain (godaddy.com)
   → Update DNS at godaddy.com

5. Get SSL Certificate:
   Automatic! (Let's Encrypt)
   Auto-renews every 3 months
```

**Time to Live:** 15 minutes  
**Cost:** $27/month all-in

### Step 3: Deploy Your App

```bash
# In your GitHub repo:

1. Create .env.production:
   cp .env.production.example .env
   # Fill in OPENAI_API_KEY and other secrets

2. Add to git:
   git add .env.production
   git commit -m "Add production config"
   git push origin main

3. DigitalOcean auto-deploys!
   Watch deployment in dashboard
   Takes ~5 minutes

4. Test:
   Visit https://yourdomain.com
   You're live! 🎉
```

### Step 4: Domain Setup

**At your domain registrar (Namecheap/GoDaddy):**

```
1. Go to DNS settings
2. Add these nameservers:
   ns1.digitalocean.com
   ns2.digitalocean.com
   ns3.digitalocean.com

3. Wait 24-48 hours for DNS to propagate
4. Test: nslookup yourdomain.com
5. Should resolve to your DigitalOcean app
```

**Or use Cloudflare (Free):**

```
1. Sign up: cloudflare.com
2. Add your domain
3. Update nameservers at registrar
4. Cloudflare points to DigitalOcean
5. Get free SSL, DDoS protection, CDN
6. Faster + more secure
```

---

## 📱 Scaling Plan (As You Grow)

### Month 1-3 (Launch Phase)
```
Cost: $32/month
Setup: 1 server, 1 database
Users: 0-100
Droplet 2GB + Managed DB
```

### Month 4-6 (Growth Phase)
```
Cost: $60/month
Setup: 1 larger server OR 2 smaller servers
Users: 100-500
Droplet 4GB + DB 4GB
Add Cloudflare CDN (free)
```

### Month 7-12 (Scale Phase)
```
Cost: $150-200/month
Setup: Load balancer + 2-3 app servers + managed DB
Users: 500-2000
Multiple Droplets + managed DB
Redis cluster for caching
```

### Year 2+ (Enterprise Phase)
```
Cost: $500+/month
Setup: Kubernetes, multiple regions, 99.9% SLA
Users: 2000+
Managed container orchestration
Multi-region replication
24/7 support
```

---

## 🔐 Security (Important!)

### Free Options
```
✅ SSL Certificate: Let's Encrypt (free)
✅ Firewall: DigitalOcean (free)
✅ DDoS Protection: Cloudflare (free tier)
✅ Backups: DigitalOcean (automatic)
✅ Encryption: Built-in (HTTPS)
```

### Must-Do Security Steps

```bash
# 1. Use strong database password
IMPORTANT: Generate 32-character random password

# 2. Enable firewall
DigitalOcean → Firewalls → Create
Allow: SSH (22), HTTP (80), HTTPS (443)
Deny: Everything else

# 3. Enable automated backups
DigitalOcean → Backups → Enable
Keep 7 daily backups (automatic)

# 4. Use Cloudflare
Free tier gives:
- DDoS protection
- Free SSL
- WAF (Web Application Firewall)
- CDN (faster delivery)

# 5. Monitor logs
DigitalOcean App Platform shows:
- Deployment logs
- Application errors
- Performance metrics
```

---

## 📊 Cost Comparison: All Options

### Budget Launch ($30/month)

| Provider | Setup | Monthly | Annual | Best For |
|----------|-------|---------|--------|----------|
| **DigitalOcean App Platform** | 15 min | $27 | $324 | **Easiest** |
| **DigitalOcean Droplet** | 30 min | $27 | $324 | More control |
| **Hetzner** | 30 min | $20 | $240 | **Cheapest** |
| **Render** | 10 min | $25 | $300 | Zero DevOps |

### Scale Phase ($150/month)

| Setup | Cost | For |
|-------|------|-----|
| 2x 4GB servers + managed DB | $150 | 500-1K users |
| 3x 2GB servers + managed DB + Redis | $150 | 1K-2K users |
| Kubernetes (managed) | $200+ | 2K+ users, auto-scale |

---

## ⚡ Quick Start (30 Minutes to Live)

```
Step 1: Buy Domain (5 min)
  → namecheap.com
  → ~$10 for .com

Step 2: Sign Up for Hosting (5 min)
  → digitalocean.com
  → Get $200 credit promo

Step 3: Deploy App (10 min)
  → Click: App Platform → Create → GitHub
  → Choose repo & branch
  → Set environment vars
  → Deploy!

Step 4: Connect Domain (5 min)
  → Point DNS to DigitalOcean
  → Enable SSL (automatic)

Step 5: Test (5 min)
  → Visit your-domain.com
  → You're live! 🎉

Total Time: 30 minutes
Total Cost: $27/month + $10/year
```

---

## 🎯 Checklist: Before Launch

### Infrastructure
- [ ] Domain registered
- [ ] Hosting account created
- [ ] Database configured
- [ ] Environment variables set
- [ ] SSL certificate active
- [ ] Backups configured

### Security
- [ ] Firewall enabled
- [ ] Database password strong
- [ ] OpenAI API key secured
- [ ] CORS configured
- [ ] Rate limiting enabled
- [ ] HTTPS enforced

### Monitoring
- [ ] Error tracking (Sentry - free tier)
- [ ] Uptime monitoring (UptimeRobot - free)
- [ ] Performance monitoring (New Relic - free tier)
- [ ] Log viewing (DigitalOcean built-in)

### Testing
- [ ] All 40+ API endpoints working
- [ ] Database connected
- [ ] Speech analysis (Whisper API) works
- [ ] Languages switching (Hungarian ↔ English)
- [ ] Reports generating
- [ ] Leaderboards updating

---

## 💡 Pro Tips

### Use Cloudflare (Free)
```
1. Sign up: cloudflare.com
2. Add your domain
3. Update nameservers
Benefits:
  ✅ Free SSL certificate
  ✅ Free CDN (faster worldwide)
  ✅ Free DDoS protection
  ✅ Free WAF (web firewall)
  ✅ Free email forwarding
```

### Save Money
```
✅ Use GitHub Student Pack (free $200 DigitalOcean)
✅ Use DigitalOcean startup credit ($200)
✅ Use Cloudflare free tier (saves $10/mo on SSL)
✅ Use Let's Encrypt (free SSL alternative)
✅ Use free monitoring tools (UptimeRobot, Sentry)
Total first year: Could be FREE with credits!
```

### Scale Cheaply
```
✅ Start with $27/month setup
✅ Scale to $60/month (double resources)
✅ Scale to $150/month (3 servers)
✅ Scale to $500+/month (enterprise)
Each step is 1-click in DigitalOcean
No code changes needed!
```

---

## 🚀 MY RECOMMENDATION

### Best Overall: DigitalOcean App Platform

Why:
- ✅ **Simplest:** No Docker/DevOps knowledge needed
- ✅ **Cheapest:** $27/month all-in
- ✅ **Fastest:** 15 minutes to live
- ✅ **Scalable:** Upgrade with 1 click
- ✅ **Reliable:** 99.9% uptime SLA
- ✅ **Secure:** Automatic SSL, backups, firewall
- ✅ **Developer-friendly:** GitHub integration

### Second Choice: Hetzner Cloud

Why:
- ✅ **Even cheaper:** $20-25/month
- ✅ **More powerful:** Better specs per dollar
- ✅ **Good for EU:** European servers
- ⚠️ **Requires DevOps:** Need to manage Docker

### Best if You Want Zero DevOps: Render

Why:
- ✅ **Automatic deployment:** GitHub → live (1 click)
- ✅ **No management:** They handle everything
- ✅ **Great UI:** Very user-friendly
- ⚠️ **Slightly pricier:** $30-40/month

---

## 📞 Support & Help

**DigitalOcean:**
- Docs: docs.digitalocean.com (excellent)
- Community: community.digitalocean.com
- Support: 24/7 ticketing system

**Namecheap:**
- Live chat: 24/7 support
- Email: Support available

**Cloudflare:**
- Docs: developers.cloudflare.com
- Community: community.cloudflare.com

---

## ✅ Summary

```
🏆 RECOMMENDED SETUP:

Domain:    Namecheap ($10/year)
Hosting:   DigitalOcean App Platform ($27/month)
SSL:       Let's Encrypt (free, automatic)
CDN:       Cloudflare (free)
Total:     $27/month + $10/year = ~$40/month

Time to launch: 30 minutes
Complexity: Very easy
Scaling: Simple 1-click upgrades
```

You're ready to launch! 🚀
