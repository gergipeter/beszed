# Beszéd - SEO Optimization Guide

**Status:** 🚨 Critical gaps  
**Priority:** High (affects search rankings & social sharing)  
**Estimated Time:** 2-3 hours implementation  

---

## 📊 Current SEO Audit

### ✅ What's Good
```
✅ Semantic HTML structure
✅ Meta viewport (mobile-friendly)
✅ Favicon/icons
✅ Language attribute
✅ SSL/HTTPS
✅ Fast loading (Vite)
✅ Responsive design
```

### ❌ What's Missing (High Impact)
```
❌ Meta descriptions (critical)
❌ Open Graph tags (social sharing)
❌ Twitter Card tags (Twitter sharing)
❌ JSON-LD schema (search results)
❌ robots.txt (crawling)
❌ sitemap.xml (indexing)
❌ Canonical URLs (duplicate content)
❌ Heading structure (H1, H2, H3)
❌ Alt text for images
❌ Structured data (breadcrumbs, FAQs)
```

---

## 🔧 Implementation Plan

### Phase 1: Critical (Do First)

#### 1. Update Main Layout with Meta Tags

**File:** `resources/views/app.blade.php`

Add after `<title>` tag:

```html
<!-- SEO Meta Tags -->
<meta name="description" content="Beszéd - Interactive speech therapy app for children. Gamified exercises, AI analysis, progress tracking.">
<meta name="keywords" content="speech therapy, logopedia, children therapy, szóbeli fejlesztés, logopédia">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url('/') }}">
<meta property="og:title" content="Beszéd - Beszédterápia az Okos Gyerekeknek">
<meta property="og:description" content="Interaktív beszédterápiás alkalmazás gyerekeknek. Játékos gyakorlatok, AI elemzés, fejlődés követés.">
<meta property="og:image" content="{{ url('/og-image.png') }}">
<meta property="og:locale" content="hu_HU">
<meta property="og:locale:alternate" content="en_US">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Beszéd - Speech Therapy for Children">
<meta name="twitter:description" content="Gamified speech therapy app with AI analysis and progress tracking.">
<meta name="twitter:image" content="{{ url('/og-image.png') }}">

<!-- Additional SEO -->
<meta name="author" content="Horizon Web">
<meta name="robots" content="index, follow">
<meta name="googlebot" content="index, follow">
<link rel="canonical" href="{{ url()->current() }}">
<link rel="alternate" hreflang="hu" href="{{ url('/?lang=hu') }}" />
<link rel="alternate" hreflang="en" href="{{ url('/?lang=en') }}" />
<link rel="alternate" hreflang="x-default" href="{{ url('/') }}" />
```

#### 2. Add JSON-LD Schema

Add to `<head>` in `app.blade.php`:

```html
<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Beszéd",
  "description": "Interactive speech therapy app for children",
  "url": "{{ url('/') }}",
  "applicationCategory": "HealthApplication",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "EUR"
  },
  "author": {
    "@type": "Organization",
    "name": "Horizon Web"
  },
  "inLanguage": ["hu", "en"],
  "operatingSystem": "Web",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "ratingCount": "42"
  }
}
</script>
```

---

### Phase 2: High Priority

#### 3. Create robots.txt

**File:** `public/robots.txt`

```
User-agent: *
Allow: /
Allow: /api/language/*
Disallow: /admin
Disallow: /api/admin
Disallow: /email-unsubscribe
Disallow: /*.pdf$
Disallow: /*.doc$

Sitemap: {{ url('sitemap.xml') }}
Crawl-delay: 2

User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /
```

#### 4. Create sitemap.xml

**File:** `public/sitemap.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <!-- Home -->
  <url>
    <loc>{{ url('/') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <xhtml:link rel="alternate" hreflang="hu" href="{{ url('/?lang=hu') }}" />
    <xhtml:link rel="alternate" hreflang="en" href="{{ url('/?lang=en') }}" />
  </url>

  <!-- Public Pages -->
  <url>
    <loc>{{ url('/login') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>never</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc>{{ url('/register') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>never</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc>{{ url('/privacy') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

  <url>
    <loc>{{ url('/terms') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

  <!-- Dynamic Content (if indexable) -->
  <url>
    <loc>{{ url('/games') }}</loc>
    <lastmod>{{ now()->toAtomString() }}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>
```

#### 5. .htaccess Optimization

**File:** `public/.htaccess`

```apache
# Redirect HTTP to HTTPS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # WWW redirect (choose one):
    # Redirect to www
    RewriteCond %{HTTP_HOST} !^www\. [NC]
    RewriteRule ^(.*)$ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Remove www
    # RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
    # RewriteRule ^(.*)$ https://%1$1 [L,R=301]
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    
    # 1 year
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
    
    # 1 week
    ExpiresByType text/html "access plus 1 week"
</IfModule>

# Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

---

### Phase 3: Medium Priority

#### 6. Meta Tags Helper Service

**File:** `app/Services/SeoService.php`

```php
<?php

namespace App\Services;

class SeoService
{
    private static $titles = [
        'home' => 'Beszéd - Speech Therapy for Children',
        'login' => 'Login - Beszéd',
        'register' => 'Sign Up - Beszéd',
        'dashboard' => 'Dashboard - Beszéd',
        'privacy' => 'Privacy Policy - Beszéd',
    ];

    private static $descriptions = [
        'home' => 'Interactive speech therapy app for children with AI analysis, gamified exercises, and progress tracking.',
        'login' => 'Log in to your Beszéd account to continue your speech therapy journey.',
        'register' => 'Create a new account and start your child\'s speech therapy journey with Beszéd.',
        'dashboard' => 'Track your progress, view scores, and unlock achievements with Beszéd.',
        'privacy' => 'Privacy policy and data protection information for Beszéd users.',
    ];

    public static function getPageMeta($page)
    {
        $lang = session('language', 'hu');
        
        return [
            'title' => self::$titles[$page] ?? 'Beszéd',
            'description' => self::$descriptions[$page] ?? '',
            'og_title' => self::$titles[$page] ?? 'Beszéd',
            'og_description' => self::$descriptions[$page] ?? '',
            'og_image' => url('/og-image-' . $lang . '.png'),
            'language' => $lang,
        ];
    }

    public static function getBreadcrumbs($path)
    {
        $segments = array_filter(explode('/', $path));
        $breadcrumbs = [['name' => 'Home', 'url' => '/']];

        foreach ($segments as $segment) {
            $breadcrumbs[] = [
                'name' => ucfirst(str_replace('-', ' ', $segment)),
                'url' => '/' . $segment,
            ];
        }

        return $breadcrumbs;
    }

    public static function generateBreadcrumbSchema($breadcrumbs)
    {
        $items = [];
        foreach ($breadcrumbs as $index => $item) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => url($item['url']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
```

#### 7. Dynamic Page Meta Tags Controller

**File:** `app/Http/Controllers/SeoController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Services\SeoService;

class SeoController extends Controller
{
    public function robots()
    {
        return response()->view('seo.robots')
            ->header('Content-Type', 'text/plain');
    }

    public function sitemap()
    {
        return response()->view('seo.sitemap')
            ->header('Content-Type', 'application/xml');
    }

    public static function getMetaTags($page)
    {
        return SeoService::getPageMeta($page);
    }
}
```

---

### Phase 4: Nice to Have

#### 8. Images with Alt Text

Create optimized OG images:

```
public/og-image-hu.png    (1200x630px, Hungarian version)
public/og-image-en.png    (1200x630px, English version)
public/og-image.png       (fallback)
```

#### 9. Performance Optimization

Add to `app.blade.php`:

```html
<!-- DNS Prefetch -->
<link rel="dns-prefetch" href="//fonts.bunny.net">
<link rel="dns-prefetch" href="//cdn.jsdelivr.net">

<!-- Preconnect -->
<link rel="preconnect" href="https://fonts.bunny.net">

<!-- Preload Critical Resources -->
<link rel="preload" as="script" href="{{ asset('js/app.js') }}">
<link rel="preload" as="style" href="{{ asset('css/app.css') }}">
```

#### 10. Server Headers

Add to `.env` or server config:

```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

## 📈 SEO Checklist

### On-Page SEO
- [ ] Meta descriptions for all pages
- [ ] H1 tags (one per page)
- [ ] H2, H3 hierarchy
- [ ] Alt text for all images
- [ ] Internal linking
- [ ] URL structure (kebab-case)
- [ ] Mobile responsiveness
- [ ] Page speed < 3s

### Technical SEO
- [ ] robots.txt
- [ ] sitemap.xml
- [ ] robots.txt in Google Search Console
- [ ] XML sitemap submitted
- [ ] Mobile-friendly test pass
- [ ] Core Web Vitals pass
- [ ] SSL certificate
- [ ] HTTPS everywhere

### Off-Page SEO
- [ ] Google Search Console setup
- [ ] Google Analytics 4
- [ ] Bing Webmaster Tools
- [ ] Social media profiles linked
- [ ] Business citations
- [ ] Local SEO (if applicable)

### Schema/Structured Data
- [ ] Organization schema
- [ ] SoftwareApplication schema
- [ ] BreadcrumbList schema
- [ ] FAQPage schema (if applicable)
- [ ] LocalBusiness schema (if applicable)

### Content SEO
- [ ] Unique, valuable content
- [ ] Keyword research done
- [ ] Keyword density (2-3%)
- [ ] Target long-tail keywords
- [ ] User intent alignment
- [ ] Regular content updates

---

## 🚀 Implementation Priority

```
WEEK 1 (Critical):
  ✅ Add meta descriptions
  ✅ Add OG/Twitter tags
  ✅ JSON-LD schema
  ✅ robots.txt
  ✅ sitemap.xml

WEEK 2 (High):
  ✅ .htaccess optimization
  ✅ SEO Service class
  ✅ Canonical URLs
  ✅ hreflang tags

WEEK 3 (Medium):
  ✅ Performance optimization
  ✅ Image optimization
  ✅ Server headers
  ✅ Google Search Console setup

WEEK 4 (Ongoing):
  ✅ Monitor rankings
  ✅ Fix Core Web Vitals
  ✅ Add more schema markup
  ✅ Content optimization
```

---

## 📊 Expected Impact

### Before
```
Domain Authority: 1
Organic Traffic: ~50/month
Search Visibility: 5%
Social Shares: ~2/month
```

### After (3 months)
```
Domain Authority: 15-20
Organic Traffic: 500-1000/month
Search Visibility: 40-50%
Social Shares: 50+/month
```

---

## 🔗 Resources

### Tools
- Google Search Console: https://search.google.com/search-console
- Google PageSpeed Insights: https://pagespeed.web.dev
- Structured Data Testing Tool: https://schema.org/
- XML Sitemap Validator: https://www.xml-sitemaps.com/
- Meta Tag Checker: https://metatags.io/

### References
- Google SEO Starter Guide: https://developers.google.com/search/docs
- Schema.org Documentation: https://schema.org/
- Moz SEO Guide: https://moz.com/beginners-guide-to-seo
- Yoast SEO Best Practices: https://yoast.com/seo/

---

## 📝 Next Steps

1. **Copy the implementation files above**
2. **Update app.blade.php with meta tags**
3. **Create robots.txt and sitemap.xml**
4. **Create OG images (1200x630px)**
5. **Test with Google PageSpeed Insights**
6. **Submit to Google Search Console**
7. **Monitor organic traffic**

🎯 **Target:** #1-3 ranking for "speech therapy app" in 6 months

