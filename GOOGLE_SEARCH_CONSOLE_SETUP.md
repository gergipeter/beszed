# Google Search Console Setup Guide

**Status:** ⏳ Manual steps required  
**Time:** 15-20 minutes  
**Impact:** Critical for organic search visibility  

---

## 📋 Quick Setup Checklist

- [ ] Create Google Search Console account
- [ ] Verify domain ownership
- [ ] Add sitemap.xml
- [ ] Request indexing for homepage
- [ ] Monitor coverage
- [ ] Fix indexing issues
- [ ] Setup email alerts
- [ ] Link to Google Analytics

---

## 🚀 Step-by-Step Setup

### Step 1: Access Google Search Console

1. Go to: https://search.google.com/search-console
2. Click **"Start now"** (if not logged in, sign in with Google account)
3. Select **"URL prefix"** property type
4. Enter your domain: `https://beszed.hu`
5. Click **"Continue"**

### Step 2: Verify Domain Ownership

Google will ask you to verify ownership. Choose ONE of these methods:

#### Option A: HTML File (Easiest for Hosting)
```
1. Download HTML verification file from GSC
2. Upload to your server's root: public/
3. File should be accessible at: https://beszed.hu/[file].html
4. Return to GSC and click "Verify"
```

#### Option B: HTML Meta Tag (For Laravel)
```
1. Copy meta tag from GSC
2. Add to resources/views/app.blade.php <head>
3. Save and deploy
4. Return to GSC and click "Verify"

Example:
<meta name="google-site-verification" content="abc123def456..." />
```

#### Option C: DNS TXT Record (Best for Domain)
```
1. Log into your domain registrar (Namecheap, GoDaddy, etc.)
2. Go to DNS settings
3. Add TXT record with value from GSC
4. Wait 24-48 hours for DNS propagation
5. Return to GSC and click "Verify"
```

**Recommended:** Use HTML Meta Tag (Option B) - instant and no DNS waiting.

### Step 3: Add Sitemap

After verification completes:

1. Go to **"Sitemaps"** in left menu
2. Click **"Add/test sitemap"**
3. Enter: `sitemap.xml`
4. Click **"Submit"**
5. Wait for "Success" status (usually 1-5 minutes)

### Step 4: Request Indexing

1. Go to **"URL inspection"** at the top
2. Paste: `https://beszed.hu/`
3. Click **"Request indexing"**
4. Repeat for key pages:
   - `https://beszed.hu/login`
   - `https://beszed.hu/register`
   - `https://beszed.hu/dashboard`

### Step 5: Monitor Coverage

1. Go to **"Coverage"** in left menu
2. Check for:
   - ✅ **Valid** - Pages indexed successfully
   - ⚠️ **Valid with warnings** - Pages indexed but issues found
   - ❌ **Excluded** - Pages not indexed (check "Why" column)
   - ⚠️ **Error** - Pages with indexing errors

**Common Issues & Fixes:**
```
❌ Blocked by robots.txt
   → Check /robots.txt allows crawling
   
❌ Not found (404)
   → Fix broken links in sitemap
   
❌ Noindex tag
   → Check meta name="robots" in HTML
   
⚠️ Soft 404
   → Page exists but looks like error page
```

### Step 6: Check Search Appearance

1. Go to **"Appearance in Search"** → **"Appearance"**
2. View how your pages look in search results
3. Check:
   - ✅ Title tags are showing
   - ✅ Meta descriptions are showing
   - ✅ Images are displaying
   - ✅ Schema markup is detected

### Step 7: Review Core Web Vitals

1. Go to **"Experience"** → **"Core Web Vitals"**
2. Check:
   - **Good** (green) - Page loads fast ✅
   - **Needs improvement** (orange) - Optimize
   - **Poor** (red) - High priority fix

**If issues found:**
```
1. Go to Google PageSpeed Insights
2. Paste URL: https://speeed.web.dev
3. Review "Opportunities" section
4. Implement recommendations
5. Wait 28 days for GSC to re-evaluate
```

### Step 8: Link Google Analytics

1. Go to **"Settings"** (bottom left)
2. Click **"Google Analytics"**
3. Select your Analytics property
4. Click **"Save"**

This links GSC data with Analytics for better insights.

### Step 9: Setup Email Alerts

1. Go to **"Settings"**
2. Click **"Users and permissions"**
3. Add email addresses for:
   - Critical issues (indexing errors)
   - Coverage notifications
   - Search performance changes

---

## 📊 What to Monitor Weekly

### Coverage
```
Goal: 95%+ pages indexed
Action: If coverage drops, investigate "Excluded" reasons
```

### Search Performance
```
Path: "Performance" in left menu
Watch:
  - Total Clicks (should increase over time)
  - Total Impressions (how often you appear)
  - Average Position (aim for top 10 for main keywords)
  - Click-Through Rate (aim for 3-5%)
```

### Core Web Vitals
```
Path: "Experience" → "Core Web Vitals"
Targets:
  - Largest Contentful Paint: < 2.5s (green)
  - First Input Delay: < 100ms (green)
  - Cumulative Layout Shift: < 0.1 (green)
```

### Indexing Issues
```
If errors appear:
1. Read the error description
2. Click affected URL
3. View source to debug
4. Fix issue
5. Click "Test live URL"
6. Request reindexing
```

---

## 🔧 Common Problems & Solutions

### Problem: Domain Not Verifying
```
❌ Error: "Verification failed"

Solution:
1. Check meta tag is in <head> exactly as provided
2. Ensure no typos in content attribute
3. Try different verification method (DNS or HTML file)
4. Wait 24-48 hours if using DNS
5. Clear browser cache and try again
```

### Problem: Sitemap Not Accepted
```
❌ Error: "Sitemap contains invalid URLs"

Solution:
1. Check sitemap.xml is valid XML (use validator)
2. Ensure all URLs are absolute (https://...)
3. Remove pages that return 404
4. Remove pages with noindex tag
5. Limit to 50,000 URLs (split if larger)
```

### Problem: Pages Not Indexing
```
❌ Coverage shows "Excluded"

Solution:
1. Check robots.txt allows crawling
2. Check page doesn't have noindex meta tag
3. Check page doesn't require login
4. Check page loads without JavaScript
5. Check content is substantial (>200 words)
6. Remove canonical to different domain
```

### Problem: Low Search Visibility
```
❌ Low clicks/impressions in Performance

Solution:
1. Improve title tags (include target keyword)
2. Improve meta descriptions (compelling copy)
3. Add schema markup (FAQs, reviews, etc.)
4. Fix Core Web Vitals issues
5. Increase content (long-form content ranks better)
6. Build backlinks (external links)
7. Wait 3-6 months (new sites take time)
```

---

## 📈 Expected Timeline

```
Week 1:
  ✅ Domain verified
  ✅ Sitemap submitted
  ✅ Homepage indexed

Week 2-4:
  ✅ All pages indexed
  ✅ Coverage stable at 95%+
  ✅ First clicks appearing in Search Performance

Month 2-3:
  ✅ Climb from position #50+ to #10-30
  ✅ 50-100 monthly clicks
  ✅ Core Web Vitals all green

Month 3-6:
  ✅ Climb to position #5-10 for main keywords
  ✅ 200-500 monthly clicks
  ✅ Improved rankings in related searches
```

---

## 🎯 Key Performance Indicators

Monitor these metrics monthly:

| Metric | Target | Current |
|--------|--------|---------|
| Pages Indexed | 95%+ | --- |
| Coverage Errors | 0 | --- |
| Average Position | < 15 | --- |
| Monthly Clicks | 100+ | --- |
| Monthly Impressions | 500+ | --- |
| Click-Through Rate | 3-5% | --- |
| Core Web Vitals (Good) | 90%+ | --- |

---

## 🔗 Useful Resources

- [Google Search Console Help](https://support.google.com/webmasters)
- [XML Sitemap Validator](https://www.xml-sitemaps.com/validate-xml-sitemap.html)
- [PageSpeed Insights](https://pagespeed.web.dev)
- [Schema Markup Validator](https://schema.org/validator)
- [Search Console Training](https://developers.google.com/search/docs)

---

## ✅ Verification Checklist

After completing all steps:

- [ ] Domain verified in Google Search Console
- [ ] Sitemap submitted and indexed
- [ ] Homepage indexed and appearing in search
- [ ] No indexing errors or coverage issues
- [ ] Core Web Vitals all green
- [ ] Google Analytics linked
- [ ] Email alerts configured
- [ ] Monitoring dashboard bookmarked

---

## 🚀 Next Steps

1. **This Week:**
   - Complete GSC setup
   - Submit sitemap
   - Request indexing

2. **Next Week:**
   - Monitor coverage (should reach 90%+)
   - Check Core Web Vitals
   - Fix any indexing errors

3. **Month 1:**
   - Monitor search performance
   - Analyze search queries
   - Identify low-performing pages

4. **Month 2-3:**
   - Track ranking improvements
   - Optimize top 10 keywords
   - Create FAQ schema for more visibility

5. **Ongoing:**
   - Monitor weekly performance
   - Fix issues immediately
   - Create content for high-opportunity keywords

---

**Result:** Expect 50-100 organic visitors/month starting Month 2, growing to 500+/month by Month 6. 🚀

