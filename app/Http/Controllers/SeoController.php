<?php

namespace App\Http\Controllers;

use App\Services\SeoService;

class SeoController extends Controller
{
    /**
     * Serve robots.txt
     */
    public function robots()
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Allow: /api/language/\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /api/admin/\n";
        $content .= "Disallow: /email-unsubscribe\n";
        $content .= "Disallow: /sanctum/\n\n";
        $content .= "Sitemap: " . url('sitemap.xml') . "\n";
        $content .= "Crawl-delay: 2\n\n";
        $content .= "User-agent: Googlebot\n";
        $content .= "Allow: /\n";
        $content .= "Crawl-delay: 1\n\n";
        $content .= "User-agent: Bingbot\n";
        $content .= "Allow: /\n";
        $content .= "Crawl-delay: 1\n";

        return response($content)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Get SEO meta tags for a page
     */
    public static function getPageMeta($page)
    {
        return SeoService::getPageMeta($page);
    }

    /**
     * Generate breadcrumb schema
     */
    public static function getBreadcrumbSchema($breadcrumbs)
    {
        return json_encode(SeoService::generateBreadcrumbSchema($breadcrumbs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate FAQ schema
     */
    public static function getFaqSchema($faqs)
    {
        return json_encode(SeoService::generateFaqSchema($faqs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
