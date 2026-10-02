<?php

namespace App\Services;

/**
 * Page meta and structured-data helpers. Kept in line with resources/views/app.blade.php: Hungarian, nothing
 * the app cannot back up (no ratings, offers or publisher), and only the pages the router has.
 */
class SeoService
{
    private static $pageMeta = [
        'home' => [
            'title' => 'Beszéd – Csillám játékai',
            'description' => 'Játékos beszéd- és iskolaelőkészítő gyakorlás 4–7 éveseknek.',
        ],
        'login' => [
            'title' => 'Belépés – Beszéd',
            'description' => 'Szülőként jelentkezz be. A gyerekek eredményei a te fiókodhoz tartoznak, és csak te látod őket.',
        ],
        'privacy' => [
            'title' => 'Adatkezelési tájékoztató – Beszéd',
            'description' => 'Hogyan kezeli a Beszéd a szülők és a gyerekek adatait.',
        ],
        'terms' => [
            'title' => 'Felhasználási feltételek – Beszéd',
            'description' => 'A Beszéd használatának feltételei.',
        ],
    ];

    /**
     * Get meta tags for a specific page
     */
    public static function getPageMeta($page)
    {
        $meta = self::$pageMeta[$page] ?? self::$pageMeta['home'];

        return [
            'title' => $meta['title'],
            'description' => $meta['description'],
            'og_title' => $meta['title'],
            'og_description' => $meta['description'],
            'og_image' => url('/og-image.svg'),
        ];
    }

    /**
     * Generate breadcrumb schema
     */
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

    /**
     * Generate FAQ schema
     */
    public static function generateFaqSchema($faqs)
    {
        $items = [];
        foreach ($faqs as $faq) {
            $items[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ];
    }
}
