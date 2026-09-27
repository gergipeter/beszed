<?php

namespace App\Services;

class SeoService
{
    private static $pageMeta = [
        'home' => [
            'title' => 'Beszéd - Interactive Speech Therapy for Children',
            'description' => 'Gamified speech therapy app with AI-powered analysis, interactive exercises, progress tracking, and rewards. For children with speech development needs.',
        ],
        'login' => [
            'title' => 'Login - Beszéd Speech Therapy',
            'description' => 'Log in to your Beszéd account to continue your child\'s speech therapy journey with interactive games and AI analysis.',
        ],
        'register' => [
            'title' => 'Sign Up - Beszéd Speech Therapy',
            'description' => 'Create a new account and start your child\'s speech therapy journey with Beszéd. Free interactive exercises and progress tracking.',
        ],
        'dashboard' => [
            'title' => 'Dashboard - Beszéd Speech Therapy',
            'description' => 'Track your child\'s progress, view speech analysis scores, and unlock achievements with Beszéd.',
        ],
        'privacy' => [
            'title' => 'Privacy Policy - Beszéd',
            'description' => 'Privacy policy and data protection information for Beszéd users and their families.',
        ],
        'terms' => [
            'title' => 'Terms of Service - Beszéd',
            'description' => 'Terms of service and conditions for using Beszéd speech therapy application.',
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
            'og_image' => url('/og-image.png'),
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

    /**
     * Generate article schema
     */
    public static function generateArticleSchema($article)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title'],
            'description' => $article['description'],
            'image' => $article['image'] ?? url('/og-image.png'),
            'datePublished' => $article['published_at'] ?? now()->toIso8601String(),
            'dateModified' => $article['updated_at'] ?? now()->toIso8601String(),
            'author' => [
                '@type' => 'Organization',
                'name' => 'Beszéd',
                'url' => url('/'),
            ],
        ];
    }
}
