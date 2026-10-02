<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7cc8ff">

    <!-- SEO Meta Tags (Hungarian: the app opens in Hungarian, see <html lang>; the wording follows manifest.webmanifest) -->
    <title>Beszéd – Csillám játékai</title>
    <meta name="description" content="Játékos beszéd- és iskolaelőkészítő gyakorlás 4–7 éveseknek.">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="bingbot" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:title" content="Beszéd – Csillám játékai">
    <meta property="og:description" content="Játékos beszéd- és iskolaelőkészítő gyakorlás 4–7 éveseknek.">
    <meta property="og:image" content="{{ url('/og-image.svg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="hu_HU">
    <meta property="og:site_name" content="Beszéd">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Beszéd – Csillám játékai">
    <meta name="twitter:description" content="Játékos beszéd- és iskolaelőkészítő gyakorlás 4–7 éveseknek.">
    <meta name="twitter:image" content="{{ url('/og-image.svg') }}">

    <!-- Canonical (the language is chosen in the app, not by URL, so there are no hreflang alternates) -->
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Performance -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link rel="preconnect" href="https://fonts.bunny.net">

    <!-- JSON-LD Structured Data: only what is true of the app itself (no ratings, offers or publisher until they are settled) -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "SoftwareApplication",
      "name": "Beszéd",
      "description": "Játékos beszéd- és iskolaelőkészítő gyakorlás 4–7 éveseknek.",
      "url": "{{ url('/') }}",
      "applicationCategory": "EducationApplication",
      "operatingSystem": "Web",
      "inLanguage": ["hu", "en"]
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
