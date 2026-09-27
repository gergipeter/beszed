<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7cc8ff">

    <!-- SEO Meta Tags -->
    <title>Beszéd - Interactive Speech Therapy for Children</title>
    <meta name="description" content="Gamified speech therapy app with AI-powered analysis, interactive exercises, progress tracking, and rewards. For children with speech development needs.">
    <meta name="keywords" content="speech therapy, logopedia, children therapy, szóbeli fejlesztés, logopédia, speech exercises">
    <meta name="author" content="Horizon Web">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="bingbot" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:title" content="Beszéd - Interactive Speech Therapy for Children">
    <meta property="og:description" content="Gamified speech therapy app with AI analysis, interactive exercises, and progress tracking for children.">
    <meta property="og:image" content="{{ url('/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="hu_HU">
    <meta property="og:locale:alternate" content="en_US">
    <meta property="og:site_name" content="Beszéd">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Beszéd - Speech Therapy for Children">
    <meta name="twitter:description" content="Interactive speech therapy app with AI analysis and gamified learning.">
    <meta name="twitter:image" content="{{ url('/og-image.png') }}">

    <!-- Canonical & Language Alternates -->
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="hu" href="{{ url('/?lang=hu') }}" />
    <link rel="alternate" hreflang="en" href="{{ url('/?lang=en') }}" />
    <link rel="alternate" hreflang="x-default" href="{{ url('/') }}" />

    <!-- Performance -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preload" as="script" href="{{ asset('js/app.js') }}">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "Beszéd",
      "description": "Interactive speech therapy app for children with AI-powered analysis",
      "url": "{{ url('/') }}",
      "applicationCategory": "HealthApplication",
      "operatingSystem": "Web",
      "inLanguage": ["hu", "en"],
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "EUR"
      },
      "author": {
        "@type": "Organization",
        "name": "Horizon Web",
        "url": "{{ url('/') }}"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "ratingCount": "42"
      }
    }
    </script>

    <!-- Organization Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Beszéd",
      "url": "{{ url('/') }}",
      "logo": "{{ url('/icons/favicon-32.png') }}",
      "description": "Speech therapy application for children",
      "sameAs": [
        "https://facebook.com/beszed",
        "https://twitter.com/beszed"
      ]
    }
    </script>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Csillám">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <script id="app-config" type="application/json">@json($appConfig)</script>
    @vite('resources/js/app.js')
</head>
<body>
    <div id="app"></div>
</body>
</html>
