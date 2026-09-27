# 🚀 Cloudflare Pages + Workers - Production Deployment

**Status:** Building... (2-5 minutes)  
**Timeline:** 20 minutes total to live  
**Cost:** FREE tier (or €20/mo Pro for unlimited)  

---

## 📋 Pre-Flight Checklist

- [ ] Cloudflare account created (https://dash.cloudflare.com)
- [ ] Domain registered & ready
- [ ] Wrangler CLI installed globally
- [ ] Logged in: `wrangler login`
- [ ] Frontend build complete
- [ ] Worker code ready (cloudflare-worker.js)
- [ ] Database credentials ready

---

## Step 1: Setup Cloudflare Account & Domain

### 1.1 Create Account
```
1. Go to https://dash.cloudflare.com
2. Sign up (free tier available)
3. Verify email
```

### 1.2 Add Domain
```
1. Cloudflare Dashboard → "Add Site"
2. Enter your domain: yourdomain.com
3. Select "Free" plan (€0/month)
4. Proceed to nameserver update
```

### 1.3 Update Nameservers
```
Cloudflare provides 2 nameservers:
  ns1.cloudflare.com
  ns2.cloudflare.com

At your registrar (GoDaddy, Namecheap, etc.):
  1. Update DNS settings
  2. Replace nameservers with Cloudflare's
  3. Save changes
  
⏱️ Wait 5-15 minutes for DNS propagation
Check: https://dns.google (search your domain)
```

### 1.4 Verify Setup
```
In Cloudflare dashboard:
  ✅ Domain status shows "Active"
  ✅ Nameservers verified
  ✅ SSL/TLS: "Flexible" or "Full"
```

---

## Step 2: Deploy Frontend to Cloudflare Pages

### 2.1 Build Frontend
```bash
# In progress - check status
npm run build

# Output folder: dist/
# Contains: index.html + all assets
```

### 2.2 Deploy to Pages
```bash
# Make sure Wrangler is logged in
wrangler login

# Deploy the dist folder
wrangler pages deploy dist

# Will ask:
#   Project name: beszed
#   Branch: production
```

### 2.3 Verify Pages Deployment
```
Cloudflare will provide:
  ✅ Project URL: https://beszed.pages.dev
  ✅ Custom domain: https://yourdomain.com (after setup)
  ✅ Deploy logs visible in dashboard
  
Test it:
  curl https://beszed.pages.dev
  # Should return HTML
```

---

## Step 3: Deploy API to Cloudflare Workers

### 3.1 Update Worker Code
```javascript
// cloudflare-worker.js is ready
// It handles:
//   • Request routing to your backend
//   • Edge caching
//   • Security headers
//   • Rate limiting
//   • CORS handling
```

### 3.2 Configure wrangler.toml
```toml
# Already configured with:
name = "beszed"
type = "javascript"
workers_dev = true
route = "https://yourdomain.com/api/*"

# Update these:
account_id = "YOUR_ACCOUNT_ID"  # From Cloudflare dashboard
zone_id = "YOUR_ZONE_ID"        # From domain settings

[env.production.vars]
API_ORIGIN = "https://your-backend.com"  # Your backend URL
```

### 3.3 Get Your IDs
```
In Cloudflare Dashboard:
  1. Account Home → Right sidebar
  2. Copy: Account ID
  3. Select domain → Overview
  4. Copy: Zone ID
  
Paste into wrangler.toml
```

### 3.4 Deploy Worker
```bash
# Deploy to production
wrangler publish --env production

# Will show:
# ✅ Uploaded worker to Cloudflare
# ✅ Published successfully
# ✅ Access at: https://api.yourdomain.com
```

---

## Step 4: Setup D1 Database (Optional)

### 4.1 Create D1 Database
```bash
# Create serverless SQL database
wrangler d1 create beszed_prod

# Output includes:
#   database_id = "xxx"
#   binding = "DB"
```

### 4.2 Update wrangler.toml
```toml
[[env.production.d1_databases]]
binding = "DB"
database_name = "beszed_prod"
database_id = "YOUR_DATABASE_ID"
```

### 4.3 Run Migrations
```bash
# Execute migrations on D1
wrangler d1 execute beszed_prod --file=./database.sql --remote

# Or upload schema:
wrangler d1 execute beszed_prod < schema.sql --remote
```

---

## Step 5: Setup KV Storage (Optional - Caching)

### 5.1 Create KV Namespace
```bash
wrangler kv:namespace create CACHE --preview=false
```

### 5.2 Update wrangler.toml
```toml
[[env.production.kv_namespaces]]
binding = "CACHE"
id = "YOUR_KV_ID"
preview_id = "YOUR_PREVIEW_ID"
```

### 5.3 Use in Worker
```javascript
// In cloudflare-worker.js
const cached = await env.CACHE.get('key')
await env.CACHE.put('key', value, { expirationTtl: 3600 })
```

---

## Step 6: Connect to Backend (Important!)

### 6.1 Update API_ORIGIN
In `wrangler.toml`:
```toml
[env.production.vars]
API_ORIGIN = "https://your-actual-backend.com"
```

### 6.2 Options for Backend

**Option A: Keep DigitalOcean Backend**
```
API_ORIGIN = "https://api.youroldhost.com"
→ Worker routes requests there
→ Edge caches responses
→ You get: edge + existing backend
```

**Option B: Deploy Backend to Railway**
```
1. Push code to GitHub
2. Connect Railway
3. Get URL: https://your-app.railway.app
4. Set: API_ORIGIN = "https://your-app.railway.app"
```

**Option C: Deploy Backend to Cloudflare Workers**
```
1. Create separate Worker for backend
2. Use D1 database + KV cache
3. Set: API_ORIGIN = "https://api.yourdomain.com"
```

---

## Step 7: Configure DNS Records

### 7.1 Update DNS Settings
In Cloudflare dashboard → DNS:

```
Type    Name    Content              Proxy
────────────────────────────────────────────
CNAME   www     yourdomain.com       Proxied
A       @       Automatic (Pages)    Proxied
TXT     @       v=spf1 include:...   DNS only
```

### 7.2 Setup Root Domain
```
Option 1: A Record
  • Type: A
  • Name: @
  • Content: 1.2.3.4 (Cloudflare IP)
  • Proxied: Yes

Option 2: CNAME
  • Type: CNAME
  • Name: @
  • Content: yourdomain.com (pages.dev)
```

### 7.3 Verify DNS
```bash
nslookup yourdomain.com
# Should resolve to Cloudflare IPs
```

---

## Step 8: Enable SSL/TLS

### 8.1 Automatic SSL
```
Cloudflare Dashboard → SSL/TLS:
  ✅ Encryption level: Full (Strict)
  ✅ Always HTTPS: On
  ✅ Minimum TLS Version: 1.2
  ✅ HSTS: Max age 12 months
  
Certificate issued automatically by Let's Encrypt
No configuration needed!
```

### 8.2 Test HTTPS
```bash
curl https://yourdomain.com
# Should redirect HTTP → HTTPS
# Should have valid certificate
```

---

## Step 9: Performance & Security Settings

### 9.1 Caching
```
Cloudflare Dashboard → Caching:
  ✅ Cache Level: Cache Everything
  ✅ Browser Cache TTL: 1 month
  ✅ Rocket Loader: Off
  ✅ Minify: CSS, JS, HTML enabled
```

### 9.2 Security
```
Firewall Rules:
  ✅ DDoS Protection: Always On
  ✅ WAF: Enable Managed Rules
  ✅ Bot Management: Challenge suspicious bots
  ✅ Rate Limiting: 10 req/sec per IP
```

### 9.3 Workers Analytics
```
Wrangler dashboard:
  • Monitor request counts
  • View error rates
  • Track response times
  • See cache hit ratios
```

---

## Step 10: Testing & Verification

### 10.1 Test Frontend
```bash
# Test Pages deployment
curl https://yourdomain.com
curl https://yourdomain.com/api/health

# Should return:
# ✅ HTML (frontend)
# ✅ JSON (API)
```

### 10.2 Test API
```bash
# Test Worker routing
curl https://yourdomain.com/api/games
curl https://yourdomain.com/api/users/me

# Should proxy to backend
# Should return API responses
```

### 10.3 Test Caching
```bash
# First request - hits origin
curl -i https://yourdomain.com/api/games

# Check headers:
# X-Cache: MISS

# Second request - hits cache
curl -i https://yourdomain.com/api/games

# Should show:
# X-Cache: HIT
# CF-Cache-Status: HIT
```

### 10.4 Test Mobile
```
Open on phone:
  https://yourdomain.com
  
Verify:
  ✅ Loads quickly
  ✅ Mobile responsive
  ✅ Games playable
  ✅ No HTTPS warnings
  ✅ Images load
```

---

## Step 11: Monitoring & Alerts

### 11.1 Setup Error Tracking
```bash
# Optional: Sentry integration
npm install @sentry/browser

# In your app:
import * as Sentry from "@sentry/browser"
Sentry.init({ dsn: "YOUR_SENTRY_DSN" })
```

### 11.2 Enable Logs
```bash
# View live logs
wrangler tail --env production

# Or in Cloudflare dashboard:
Workers → Logs
```

### 11.3 Performance Monitoring
```
Use built-in tools:
  • Cloudflare Analytics
  • Worker Analytics
  • Pages Analytics
  • Browser DevTools
```

---

## Common Issues & Fixes

### ❌ "Zone not verified"
```
Fix: Wait 5-15 minutes for DNS propagation
Check: https://dns.google
```

### ❌ "API returning 404"
```
Fix: Check API_ORIGIN in wrangler.toml
Verify: Backend is running and accessible
Test: curl https://your-backend.com/api/health
```

### ❌ "CORS errors"
```
Fix: cloudflare-worker.js has CORS headers
Verify: Access-Control-Allow-Origin is set
Test: curl -i https://yourdomain.com/api/games
```

### ❌ "High latency"
```
Fix: Enable caching (Cache Level: Cache Everything)
Check: Cache hit ratio >90%
Monitor: CF-Cache-Status header
```

### ❌ "Build fails"
```
Fix: Check npm run build locally
Verify: dist/ folder exists
Check: Node version compatibility
```

---

## Final Checklist

```
BEFORE DEPLOYING:
☐ Cloudflare account created
☐ Domain registered & nameservers updated
☐ Wrangler logged in
☐ npm run build successful
☐ dist/ folder exists
☐ wrangler.toml configured
☐ account_id & zone_id set

DEPLOYMENT:
☐ wrangler pages deploy dist (successful)
☐ wrangler publish --env production (successful)
☐ DNS records configured
☐ SSL/TLS enabled

TESTING:
☐ https://yourdomain.com loads
☐ API endpoints respond
☐ Caching working (X-Cache: HIT)
☐ Mobile responsive
☐ HTTPS working
☐ No console errors
☐ Performance good

MONITORING:
☐ Logs accessible
☐ Errors being tracked
☐ Analytics enabled
☐ Alerts configured
☐ Backup plan ready
```

---

## Success! 🎉

```
Your platform is now live at: https://yourdomain.com

You have:
  ✅ Frontend on Cloudflare Pages (global CDN)
  ✅ API on Cloudflare Workers (edge computing)
  ✅ Database: Your choice (D1, Railway, DigitalOcean)
  ✅ SSL/TLS: Automatic (Let's Encrypt)
  ✅ DDoS Protection: Active
  ✅ 99.99% uptime SLA
  ✅ Global edge nodes (200+)

Ready for millions of users!
```

---

## Next Steps

1. **Monitor first 24 hours**
   - Watch error logs
   - Check analytics
   - Test all features

2. **Setup additional services** (optional)
   - Email (Mailgun)
   - Payments (Stripe)
   - Push notifications (Firebase)
   - Analytics (Google Analytics)

3. **Scale when needed**
   - Add database replicas
   - Increase KV limits
   - Monitor and optimize
   - Plan capacity growth

4. **Marketing & Launch**
   - Create landing page
   - Setup SEO
   - Social media
   - User acquisition

---

**Congratulations! Your speech therapy platform is live! 🚀**

See: CLOUDFLARE_DEPLOYMENT.md for more options.

Let me know if you need help with any step!
