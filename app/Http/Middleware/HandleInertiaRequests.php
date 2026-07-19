<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $company = Schema::hasTable('company_settings')
            ? CompanySetting::query()->first(['company_name', 'logo', 'favicon'])
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'courier_id' => $request->user()->courier?->id,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'company' => [
                'name' => $company?->company_name ?: 'InvoFlow',
                'logo_url' => $company?->logo ? '/storage/'.ltrim($company->logo, '/') : null,
                'favicon_url' => $company?->favicon ? '/storage/'.ltrim($company->favicon, '/') : null,
            ],
            'webPush' => [
                'enabled' => filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key')),
                'publicKey' => config('services.webpush.public_key'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
