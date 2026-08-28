<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Deterministic, offline-friendly outgoing webhook delivery.
 *
 * Payloads are signed with an HMAC-SHA256 digest of the canonical JSON body
 * using the subscription secret, exposed to receivers as the
 * `X-Webhook-Signature` header. Every attempt is recorded in
 * `webhook_deliveries` so outbound traffic is auditable and queryable.
 */
final class WebhookService
{
    public function __construct(private readonly int $timeout = 10) {}

    /**
     * Deliver an event to a subscription and record the attempt.
     *
     * @param  array<string, mixed>  $data
     */
    public function deliver(WebhookSubscription $subscription, string $event, array $data): WebhookDelivery
    {
        $body = [
            'event' => $event,
            'sent_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        $canonical = $this->encode($body);
        $signature = hash_hmac('sha256', $canonical, $subscription->secret);

        $delivery = WebhookDelivery::create([
            'webhook_subscription_id' => $subscription->id,
            'event' => $event,
            'payload' => $body,
            'status' => 'pending',
            'attempts' => 1,
        ]);

        try {
            $response = $this->send($subscription->url, $body, $signature);

            $delivery->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'http_status' => $response->status(),
                'response_body' => $this->truncate((string) $response->body()),
                'delivered_at' => now(),
            ]);
        } catch (ConnectionException $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'Receiver unreachable.',
                'delivered_at' => now(),
            ]);
        }

        return $delivery->refresh();
    }

    /**
     * Compute the signature a receiver should see, for verification/testing.
     *
     * @param  array<string, mixed>  $body
     */
    public function sign(WebhookSubscription $subscription, array $body): string
    {
        return hash_hmac('sha256', $this->encode($body), $subscription->secret);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function encode(array $body): string
    {
        return (string) json_encode($body, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $url, array $body, string $signature): Response
    {
        return Http::withHeaders([
            'X-Webhook-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->asJson()
            ->post($url, $body);
    }

    private function truncate(string $value, int $max = 2000): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max).'…(truncated)';
    }
}
