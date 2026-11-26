<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'id' => '/',
            'name' => config('pwa.name'),
            'short_name' => config('pwa.short_name'),
            'description' => config('pwa.description'),
            'lang' => config('pwa.lang'),
            'start_url' => config('pwa.start_url'),
            'scope' => config('pwa.scope'),
            'display' => 'standalone',
            'theme_color' => config('pwa.theme_color'),
            'background_color' => config('pwa.background_color'),
            'icons' => [
                ['src' => asset('assets/aura/pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('assets/aura/pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('assets/aura/pwa/icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('assets/aura/pwa/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function worker(): Response
    {
        $source = File::get(resource_path('pwa/sw.js'));
        $cachePrefix = config('pwa.cache_namespace');
        $cacheName = $cachePrefix.config('pwa.cache_version');
        $source = str_replace(
            ['__AURA_CACHE_NAME__', '__AURA_CACHE_PREFIX__', '__AURA_RETIRE__'],
            [json_encode($cacheName, JSON_THROW_ON_ERROR), json_encode($cachePrefix, JSON_THROW_ON_ERROR), config('pwa.worker_retirement') ? 'true' : 'false'],
            $source,
        );

        return response($source, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, max-age=0',
            'Service-Worker-Allowed' => '/',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
