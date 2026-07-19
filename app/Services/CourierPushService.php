<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\CourierDelivery;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class CourierPushService
{
    public function sendNewTask(CourierDelivery $delivery): void
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        $subject = config('services.webpush.subject');

        if (! filled($publicKey) || ! filled($privateKey) || ! filled($subject)) {
            return;
        }

        $delivery->loadMissing(['invoice.customer', 'courier.user.webPushSubscriptions']);
        $subscriptions = $delivery->courier?->user?->webPushSubscriptions;

        if (! $subscriptions || $subscriptions->isEmpty()) {
            return;
        }

        $company = CompanySetting::query()->first(['company_name', 'logo', 'favicon']);
        $iconPath = $company?->favicon ?: $company?->logo;
        $iconUrl = $iconPath ? url('/storage/'.ltrim($iconPath, '/')) : url('/favicon.ico');
        $invoice = $delivery->invoice;
        $customer = $invoice?->billing_company ?: $invoice?->billing_name ?: $invoice?->customer?->company_name ?: $invoice?->customer?->name;
        $payload = json_encode([
            'title' => 'Tugas Pengiriman Baru',
            'body' => trim(($invoice?->invoice_number ?: 'Invoice baru').' • '.($customer ?: 'Pelanggan')),
            'icon' => $iconUrl,
            'badge' => $iconUrl,
            'tag' => 'courier-task-'.$delivery->id,
            'url' => route('courier.tasks.index'),
            'deliveryId' => $delivery->id,
            'invoiceNumber' => $invoice?->invoice_number,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ], ['TTL' => 86400, 'urgency' => 'high']);

            foreach ($subscriptions as $storedSubscription) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $storedSubscription->endpoint,
                        'publicKey' => $storedSubscription->public_key,
                        'authToken' => $storedSubscription->auth_token,
                        'contentEncoding' => $storedSubscription->content_encoding,
                    ]);
                    $report = $webPush->sendOneNotification($subscription, $payload, [
                        'topic' => 'task-'.$delivery->id,
                    ]);

                    if ($report->isSubscriptionExpired()) {
                        $storedSubscription->delete();
                    } elseif ($report->isSuccess()) {
                        $storedSubscription->update(['last_used_at' => now()]);
                    } else {
                        Log::warning('Web Push kurir gagal dikirim.', [
                            'delivery_id' => $delivery->id,
                            'subscription_id' => $storedSubscription->id,
                            'reason' => $report->getReason(),
                        ]);
                    }
                } catch (Throwable $exception) {
                    Log::warning('Web Push kurir gagal diproses.', [
                        'delivery_id' => $delivery->id,
                        'subscription_id' => $storedSubscription->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Konfigurasi Web Push tidak dapat digunakan.', [
                'delivery_id' => $delivery->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
