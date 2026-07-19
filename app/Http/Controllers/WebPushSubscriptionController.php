<?php

namespace App\Http\Controllers;

use App\Models\WebPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebPushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->ensureCourier($request);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
            'keys.p256dh' => ['required', 'string', 'max:1024'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::in(['aesgcm', 'aes128gcm'])],
        ]);

        $subscription = WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['subscribed' => true, 'id' => $subscription->id]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->ensureCourier($request);
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:4096']]);

        $request->user()->webPushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    private function ensureCourier(Request $request): void
    {
        abort_unless($request->user()?->hasRole('Kurir') && $request->user()->courier, 403);
    }
}
