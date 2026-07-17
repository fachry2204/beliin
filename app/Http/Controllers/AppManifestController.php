<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AppManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $company = Schema::hasTable('company_settings')
            ? CompanySetting::query()->first(['company_name', 'logo', 'favicon', 'updated_at'])
            : null;

        $name = $company?->company_name ?: config('app.name', 'Sistem Invoice');
        $icon = $company?->favicon ?: $company?->logo;
        $iconUrl = $icon
            ? url('/storage/'.ltrim($icon, '/')).'?v='.$company->updated_at?->timestamp
            : asset('favicon.ico');
        $extension = strtolower(pathinfo((string) $icon, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/x-icon',
        };

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => Str::limit($name, 30, ''),
            'description' => "Aplikasi invoice dan keuangan {$name}",
            'lang' => 'id-ID',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => '#0ea5e9',
            'icons' => [[
                'src' => $iconUrl,
                'sizes' => 'any',
                'type' => $mime,
                'purpose' => 'any',
            ]],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
