<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Language Service
 * Robust, scalable multi-language support system
 * Supports: Hungarian (hu), English (en), and extensible for more
 */
class LanguageService
{
    private static string $defaultLanguage = 'hu';
    private static array $supportedLanguages = ['hu', 'en'];
    private static string $cachePrefix = 'language:';
    private static int $cacheTTL = 86400; // 24 hours

    /**
     * Get all supported languages
     */
    public static function getSupportedLanguages(): array
    {
        return [
            'hu' => [
                'name' => 'Magyar',
                'native_name' => 'Magyar',
                'flag' => '🇭🇺',
                'direction' => 'ltr',
                'region' => 'HU',
            ],
            'en' => [
                'name' => 'English',
                'native_name' => 'English',
                'flag' => '🇬🇧',
                'direction' => 'ltr',
                'region' => 'GB',
            ],
        ];
    }

    /**
     * Check if language is supported
     */
    public static function isSupported(string $language): bool
    {
        return in_array($language, self::$supportedLanguages);
    }

    /**
     * Get default language
     */
    public static function getDefaultLanguage(): string
    {
        return self::$defaultLanguage;
    }

    /**
     * Set default language
     */
    public static function setDefaultLanguage(string $language): void
    {
        if (self::isSupported($language)) {
            self::$defaultLanguage = $language;
            Cache::forget(self::$cachePrefix . 'default');
            Cache::put(self::$cachePrefix . 'default', $language, self::$cacheTTL);
        }
    }

    /**
     * Get translations for a key (cached)
     */
    public static function get(string $key, string $language = null): array
    {
        $language = $language ?? self::$defaultLanguage;

        if (!self::isSupported($language)) {
            $language = self::$defaultLanguage;
        }

        // Try cache first
        $cacheKey = self::$cachePrefix . $language . ':' . $key;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Load from translations
        $translations = self::loadTranslations($key, $language);

        // Cache for 24 hours
        Cache::put($cacheKey, $translations, self::$cacheTTL);

        return $translations;
    }

    /**
     * Load translations from language files or database
     */
    private static function loadTranslations(string $key, string $language): array
    {
        // Try language file first (resources/lang/{language}/{key}.json)
        $filePath = resource_path("lang/{$language}/{$key}.json");

        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true) ?? [];
        }

        // Fallback to default language
        if ($language !== self::$defaultLanguage) {
            $defaultPath = resource_path("lang/" . self::$defaultLanguage . "/{$key}.json");
            if (file_exists($defaultPath)) {
                return json_decode(file_get_contents($defaultPath), true) ?? [];
            }
        }

        return [];
    }

    /**
     * Get a specific translation value
     */
    public static function trans(string $key, string $locale = null): string
    {
        $language = $locale ?? self::$defaultLanguage;
        $parts = explode('.', $key);
        $file = array_shift($parts);

        $translations = self::get($file, $language);

        foreach ($parts as $part) {
            if (is_array($translations) && isset($translations[$part])) {
                $translations = $translations[$part];
            } else {
                return $key; // Return key if translation not found
            }
        }

        return $translations ?? $key;
    }

    /**
     * Clear language cache
     */
    public static function clearCache(): void
    {
        Cache::forget(self::$cachePrefix . '*');
    }

    /**
     * Add new language (for future expansion)
     */
    public static function addLanguage(string $code, array $metadata): bool
    {
        // Validate language code (2-3 chars)
        if (!preg_match('/^[a-z]{2,3}$/', $code)) {
            return false;
        }

        // Check if already exists
        if (self::isSupported($code)) {
            return false;
        }

        // Add to supported languages
        self::$supportedLanguages[] = $code;

        // Create language directory
        $langPath = resource_path("lang/{$code}");
        if (!is_dir($langPath)) {
            mkdir($langPath, 0755, true);
        }

        // Store metadata
        Cache::put(self::$cachePrefix . "meta:{$code}", $metadata, self::$cacheTTL);

        return true;
    }

    /**
     * Get all translation keys available
     */
    public static function getAvailableKeys(string $language = null): array
    {
        $language = $language ?? self::$defaultLanguage;
        $langPath = resource_path("lang/{$language}");

        if (!is_dir($langPath)) {
            return [];
        }

        $keys = [];
        $files = scandir($langPath);

        foreach ($files as $file) {
            if (str_ends_with($file, '.json')) {
                $key = str_replace('.json', '', $file);
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Export translations as JSON (for frontend)
     */
    public static function exportToJSON(string $language = null): string
    {
        $language = $language ?? self::$defaultLanguage;
        $keys = self::getAvailableKeys($language);
        $all = [];

        foreach ($keys as $key) {
            $all[$key] = self::get($key, $language);
        }

        return json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get browser language preference
     */
    public static function getBrowserLanguage(): string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return self::$defaultLanguage;
        }

        $languages = [];
        preg_match_all('/([a-z]{2,3})(?:-[a-z]{2})?(?:;q=([0-9.]+))?/i',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);

        for ($i = 0; $i < count($matches[1]); $i++) {
            $lang = strtolower($matches[1][$i]);
            $q = $matches[2][$i] ?? 1;

            if (self::isSupported($lang)) {
                $languages[$lang] = (float)$q;
            }
        }

        arsort($languages);
        return key($languages) ?? self::$defaultLanguage;
    }
}
