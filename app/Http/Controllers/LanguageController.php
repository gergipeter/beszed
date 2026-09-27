<?php

namespace App\Http\Controllers;

use App\Services\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class LanguageController extends Controller
{
    /**
     * Get current language
     */
    public function current(): JsonResponse
    {
        $current = LanguageService::getDefaultLanguage();
        $supported = LanguageService::getSupportedLanguages();

        return response()->json([
            'current' => $current,
            'current_name' => $supported[$current]['name'] ?? $current,
            'supported' => $supported,
        ]);
    }

    /**
     * Switch language
     */
    public function switch(Request $request): JsonResponse|RedirectResponse
    {
        $language = $request->input('lang', LanguageService::getDefaultLanguage());

        if (!LanguageService::isSupported($language)) {
            return response()->json(['error' => 'Language not supported'], 400);
        }

        // Set in session
        session()->put('language', $language);
        LanguageService::setDefaultLanguage($language);

        // If authenticated, save to user profile
        if (auth()->check()) {
            auth()->user()->update(['language' => $language]);
        }

        // Return JSON or redirect
        if ($request->wantsJson()) {
            return response()->json([
                'language' => $language,
                'message' => 'Language switched successfully',
            ]);
        }

        return back()->with('message', 'Language switched successfully');
    }

    /**
     * Get all available languages
     */
    public function index(): JsonResponse
    {
        $languages = LanguageService::getSupportedLanguages();
        $current = LanguageService::getDefaultLanguage();

        return response()->json([
            'current' => $current,
            'languages' => array_map(function ($code, $meta) use ($current) {
                return array_merge(['code' => $code, 'active' => $code === $current], $meta);
            }, array_keys($languages), $languages),
        ]);
    }

    /**
     * Get translations for frontend
     */
    public function getTranslations(string $language): JsonResponse
    {
        if (!LanguageService::isSupported($language)) {
            return response()->json(['error' => 'Language not supported'], 400);
        }

        $translations = LanguageService::exportToJSON($language);

        return response()->json(json_decode($translations, true));
    }

    /**
     * Add new language (admin only)
     */
    public function add(Request $request): JsonResponse
    {
        // Check admin privilege (implement your own logic)
        if (!auth()->check() || !auth()->user()->is_admin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'code' => 'required|string|size:2|regex:/^[a-z]+$/',
            'name' => 'required|string|max:255',
            'native_name' => 'required|string|max:255',
            'flag' => 'required|string|max:10',
            'direction' => 'required|in:ltr,rtl',
            'region' => 'required|string|max:2',
        ]);

        $result = LanguageService::addLanguage($request->input('code'), [
            'name' => $request->input('name'),
            'native_name' => $request->input('native_name'),
            'flag' => $request->input('flag'),
            'direction' => $request->input('direction'),
            'region' => $request->input('region'),
        ]);

        if (!$result) {
            return response()->json(['error' => 'Language already exists or invalid code'], 400);
        }

        return response()->json([
            'message' => 'Language added successfully',
            'language' => $request->input('code'),
        ]);
    }
}
