# 🚀 Beszéd SEO Launch Checklist

**Status:** ✅ Ready for Launch  
**Total Tasks:** 25  
**Time to Complete:** 2-3 hours  
**Launch Date:** Today  

---

## ✅ COMPLETED (Auto-Implemented)

### Technical SEO ✅
- [x] Meta descriptions added (155 chars each)
- [x] OG tags (Open Graph for Facebook)
- [x] Twitter Card tags
- [x] JSON-LD structured data (SoftwareApplication + Organization)
- [x] Hreflang tags (Hungarian/English alternates)
- [x] Canonical URLs
- [x] robots.txt created
- [x] sitemap.xml created
- [x] OG images created (SVG format)
- [x] Performance preloading (DNS prefetch, preconnect)
- [x] SEO Service class created
- [x] SeoController with routes

### Code Quality ✅
- [x] Clean, maintainable code
- [x] No breaking changes
- [x] Backward compatible
- [x] Follows Laravel conventions
- [x] All changes committed to git

---

## ⏳ TODO (Manual Steps - 2-3 Hours)

### Phase 1: Google Search Console (30 min)

**Step 1: Create & Verify Domain**
```
Time: 10 min

[ ] Go to: https://search.google.com/search-console
[ ] Click "Start now"
[ ] Choose "URL prefix" property type
[ ] Enter: https://beszed.hu
[ ] Click "Continue"
[ ] Choose verification method:
    [ ] A) HTML Meta Tag (fastest - recommended)
    [ ] B) HTML File Upload  
    [ ] C) DNS TXT Record (slow - 24-48h)

If using Meta Tag (A):
  [ ] Copy meta tag from GSC
  [ ] Add to resources/views/app.blade.php <head>
  [ ] Deploy to production
  [ ] Return to GSC, click "Verify"
  [ ] Wait for "Verification successful"

Time: 10 min total
```

**Step 2: Submit Sitemap**
```
Time: 5 min

[ ] Open GSC → Left menu → "Sitemaps"
[ ] Click "Add/test sitemap"
[ ] Enter: sitemap.xml
[ ] Click "Submit"
[ ] Check status changes to "Success" (wait 1-5 min)
[ ] Note the date for records

Time: 5 min total
```

**Step 3: Request Indexing for Homepage**
```
Time: 5 min

[ ] Click URL Inspection at top
[ ] Paste: https://beszed.hu/
[ ] Click "Request indexing"
[ ] Repeat for:
    [ ] https://beszed.hu/login
    [ ] https://beszed.hu/register
    [ ] https://beszed.hu/dashboard

Time: 5 min total
```

**Step 4: Setup Email Alerts**
```
Time: 10 min

[ ] Go to Settings (bottom left)
[ ] Click "Users and permissions"  
[ ] Add your email:
    [ ] Critical indexing issues
    [ ] Coverage problems
    [ ] Search performance changes
[ ] Frequency: Immediate for critical, weekly for others
[ ] Save

Time: 10 min total
```

### Phase 2: Google Analytics Setup (20 min)

**Step 1: Link GSC to Analytics**
```
Time: 5 min

[ ] Open GSC → Settings (bottom left)
[ ] Look for "Google Analytics"
[ ] Click the property dropdown
[ ] Select your Analytics view
[ ] Click "Save"
[ ] Check it's connected (may take 24-48h for data)

Time: 5 min
```

**Step 2: Create Custom Alerts**
```
Time: 10 min

[ ] Open: https://analytics.google.com
[ ] Go to Customization → Custom alerts
[ ] Create alert: "Organic users drop > 25%"
[ ] Create alert: "Bounce rate > 70%"
[ ] Create alert: "Conversion rate drops > 30%"
[ ] Set to email you weekly

Time: 10 min
```

**Step 3: Save Segment for Organic Traffic**
```
Time: 5 min

[ ] Go to Segments (left sidebar)
[ ] Click "+ Create"
[ ] Name: "Organic Traffic"
[ ] Condition: Source exactly matches: (organic)
[ ] Save
[ ] Use this segment for weekly reports

Time: 5 min
```

### Phase 3: Performance Optimization (30 min)

**Step 1: Baseline Core Web Vitals**
```
Time: 10 min

[ ] Open: https://pagespeed.web.dev
[ ] Test: https://beszed.hu/
[ ] Record baseline scores:
    Mobile Performance: ___
    Desktop Performance: ___
    Core Web Vitals: GREEN ☐ ORANGE ☐ RED ☐
[ ] Screenshot for records
[ ] Review "Opportunities" section
[ ] Note top 3 recommendations

Time: 10 min
```

**Step 2: Fix Any Red Metrics**
```
Time: 15 min (if needed)

If Core Web Vitals shows RED:
  [ ] Identify which metric:
      ☐ Largest Contentful Paint (LCP)
      ☐ First Input Delay (FID)
      ☐ Cumulative Layout Shift (CLS)
  
  [ ] Review top opportunity from PageSpeed
  [ ] Implement fix (usually quick wins)
  [ ] Deploy to production
  [ ] Wait 10 min, test again
  [ ] If still RED, implement next opportunity
  
Target: All GREEN within 24 hours
```

**Step 3: Monitor Mobile Performance**
```
Time: 5 min

[ ] Test on actual mobile device
[ ] Check app loads in < 3 seconds
[ ] Check navigation is smooth
[ ] Check images load properly
[ ] Check forms are easy to use
[ ] Note any issues for fixing

Time: 5 min
```

### Phase 4: Content Optimization (1 hour)

**Step 1: Review Page Titles**
```
Time: 15 min

For each key page, check:
[ ] Homepage
    Current: ___
    Suggested: Beszéd - Interactive Speech Therapy for Children
    
[ ] Login
    Current: ___
    Suggested: Login - Beszéd Speech Therapy
    
[ ] Register  
    Current: ___
    Suggested: Sign Up - Beszéd Speech Therapy
    
[ ] Dashboard
    Current: ___
    Suggested: Dashboard - Beszéd Speech Therapy

All titles should:
  ✅ Include main keyword
  ✅ Include brand name (Beszéd)
  ✅ Be under 60 characters
  ✅ Be unique per page

Time: 15 min
```

**Step 2: Review Meta Descriptions**
```
Time: 15 min

For each key page, check:
[ ] Homepage
    Current: ✅ Done
    
[ ] Login
    Current: ✅ Done
    
[ ] Register
    Current: ✅ Done
    
[ ] Dashboard
    Current: ✅ Done

All descriptions should:
  ✅ Include target keyword
  ✅ Include call-to-action
  ✅ Be 150-160 characters
  ✅ Unique per page
  ✅ NOT duplicate other pages

Time: 15 min
```

**Step 3: Check Page Content Quality**
```
Time: 15 min

For each page:
[ ] Homepage
    [ ] Has clear value proposition ✅
    [ ] Has 3+ unique sections
    [ ] Has internal links
    [ ] Has engaging visuals
    [ ] Has clear CTA
    
[ ] Login
    [ ] Clear form labels
    [ ] Help text if needed
    [ ] Social login options
    
[ ] Register
    [ ] Clear value prop above form
    [ ] Only essential fields
    [ ] Privacy assurance
    
[ ] Dashboard
    [ ] Valuable user content
    [ ] Clear navigation
    [ ] Shows user progress

Content should:
  ✅ Answer user intent
  ✅ Be 300+ words (aim for 1000+)
  ✅ Have clear structure (H1, H2, H3)
  ✅ Include target keywords naturally
  ✅ Have internal links (2-3 per page)

Time: 15 min
```

**Step 4: Add Schema Markup to Key Pages**
```
Time: 15 min

Option 1: Use SeoService helper (easiest)
[ ] Review SeoController.php
[ ] Copy schema generators:
    - generateBreadcrumbSchema()
    - generateFaqSchema()
    - generateArticleSchema()

Option 2: Manual JSON-LD
[ ] Add FAQ schema if you have FAQs
[ ] Add BreadcrumbList schema for navigation
[ ] Add Article schema for blog posts
[ ] Add LocalBusiness if applicable

To implement:
  [ ] Identify pages needing schema
  [ ] Use schema.org to create JSON-LD
  [ ] Add to page <head>
  [ ] Test with https://schema.org/validator
  [ ] Deploy

Time: 15 min
```

### Phase 5: Monitoring Setup (30 min)

**Step 1: Bookmark Tools**
```
Time: 5 min

Save these bookmarks:
  [ ] GSC Performance: https://search.google.com/search-console/performance/search-analytics
  [ ] GSC Coverage: https://search.google.com/search-console/coverage
  [ ] Analytics: https://analytics.google.com/analytics/web/
  [ ] PageSpeed: https://pagespeed.web.dev/
  [ ] Schema Validator: https://schema.org/validator
  [ ] Rank Tracker: https://www.ahrefs.com/site-explorer

Time: 5 min
```

**Step 2: Setup Weekly Monitoring**
```
Time: 15 min

Every Monday morning (15 min):
[ ] Open Google Search Console
    [ ] Check last 7 days performance
    [ ] Check coverage status
    [ ] Check for new errors
[ ] Open Google Analytics
    [ ] Check organic traffic
    [ ] Check top landing pages
    [ ] Check bounce rate
[ ] Note any changes in MONITORING_DASHBOARD.md

Time: 15 min
```

**Step 3: Setup Monthly Review**
```
Time: 10 min

Every first day of month:
[ ] Pull monthly report from GSC
[ ] Pull monthly report from Analytics
[ ] Complete MONITORING_DASHBOARD.md monthly template
[ ] Identify top opportunities
[ ] Plan optimizations for next month

Time: 10 min
```

---

## 📊 Timeline & Expectations

### Week 1
```
✅ Setup Complete
  - GSC verified
  - Sitemap submitted
  - Homepage indexed (24-48h)
  
Performance:
  - Clicks: 0-5
  - Impressions: 0-20
  - Coverage: Homepage only
```

### Week 2-4
```
✅ Indexing Complete
  - All pages indexed
  - Coverage: 95%+
  
Performance:
  - Clicks: 5-20
  - Impressions: 20-100
  - Average position: #50+
```

### Month 2
```
✅ Growth Starts
  - First keywords ranking (#10-30)
  - Core Web Vitals stable
  
Performance:
  - Clicks: 20-50
  - Impressions: 100-300
  - Average position: #20-40
```

### Month 3-6
```
✅ Acceleration
  - Top 10 keywords ranking
  - Some #1-3 rankings
  
Performance:
  - Clicks: 50-500
  - Impressions: 300-2000
  - Average position: #5-15
```

---

## 🎯 Success Criteria

### Launch (This Week)
```
[ ] Domain verified in GSC
[ ] Sitemap submitted successfully
[ ] Homepage indexed
[ ] Core Web Vitals all GREEN
[ ] No indexing errors
[ ] Email alerts configured
[ ] Monitoring dashboard set up
```

### Month 1
```
[ ] 95%+ pages indexed
[ ] 0 coverage errors
[ ] 10-20 clicks/week
[ ] First keywords ranking (#30-50)
[ ] No broken pages
[ ] All meta tags showing in search
```

### Month 2-3
```
[ ] 50-100 clicks/week
[ ] Top 10 keywords ranking (#10-30)
[ ] Improved average position
[ ] 200+ monthly organic users
[ ] Click-through rate 2-3%
[ ] Zero coverage issues
```

### Month 3-6
```
[ ] 100-200 clicks/week
[ ] Top 5 keywords ranking (#1-10)
[ ] Some #1-3 rankings
[ ] 500+ monthly organic users
[ ] Click-through rate 3-5%
[ ] Stable Core Web Vitals
```

---

## 📋 Document Reference

Created for you:

1. **SEO_OPTIMIZATION_GUIDE.md** - Full technical guide
2. **GOOGLE_SEARCH_CONSOLE_SETUP.md** - GSC setup walkthrough
3. **MONITORING_DASHBOARD.md** - Weekly/monthly tracking
4. **SEO_LAUNCH_CHECKLIST.md** - This checklist

---

## 🚨 Troubleshooting

### If Domain Won't Verify
```
[ ] Check meta tag is exactly in <head>
[ ] Check no typos in verification content
[ ] Clear browser cache
[ ] Try different browser
[ ] Try DNS verification method (wait 24-48h)
[ ] Contact Google support if still stuck
```

### If Sitemap Won't Submit
```
[ ] Validate XML: https://www.xml-sitemaps.com/validate-xml-sitemap.html
[ ] Check all URLs are absolute (https://...)
[ ] Remove any 404 pages from sitemap
[ ] Remove any noindex pages from sitemap
[ ] Test robots.txt allows sitemap path
```

### If Pages Not Indexing
```
[ ] Check robots.txt allows crawling
[ ] Check pages don't have noindex tag
[ ] Check pages don't require login
[ ] Check pages have substantial content (200+ words)
[ ] Check pages load without JavaScript
[ ] Request indexing manually in GSC
```

### If Traffic Not Improving
```
[ ] Wait minimum 2-4 weeks (new sites take time)
[ ] Check average position (need top 30 to get clicks)
[ ] Improve title tags (add keyword naturally)
[ ] Improve meta descriptions (make compelling)
[ ] Add more content to pages
[ ] Build internal links
[ ] Check competitors - are they stronger?
[ ] Check Core Web Vitals - all green?
```

---

## ✅ Final Checklist

Before declaring "launch complete":

```
Technical:
  [ ] All meta tags added
  [ ] All OG images in place
  [ ] Robots.txt working
  [ ] Sitemap.xml valid
  [ ] Core Web Vitals GREEN
  [ ] No 404s or errors
  
Google Setup:
  [ ] Domain verified in GSC
  [ ] Sitemap submitted
  [ ] Homepage indexed
  [ ] Email alerts active
  [ ] Linked to Analytics
  
Monitoring:
  [ ] Weekly dashboard bookmark ready
  [ ] Monthly checklist prepared
  [ ] Analytics alerts configured
  [ ] GSC alerts configured
  
Content:
  [ ] All titles optimized
  [ ] All descriptions optimized
  [ ] Key pages have schema markup
  [ ] Internal linking in place
  [ ] No broken links
  
Performance:
  [ ] Mobile speed tested
  [ ] Desktop speed tested
  [ ] Images optimized
  [ ] Caching enabled
  [ ] Compression enabled
```

---

## 🎉 Launch Complete!

Once all items are checked:

1. **Announce to team:** "SEO optimization complete and monitoring live"
2. **Send email to stakeholders:** Include timeline and expectations
3. **Set calendar reminders:** Weekly (Monday), Monthly (1st)
4. **Celebrate!** 🚀

Expected results by Month 3: **500+ monthly organic visitors**

---

**Start Date:** ___________________  
**Completion Date:** ___________________  
**Completed By:** ___________________  

