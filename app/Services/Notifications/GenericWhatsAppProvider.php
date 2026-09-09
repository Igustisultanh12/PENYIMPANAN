<?php

namespace App\Services\Notifications;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericWhatsAppProvider implements WhatsAppNotificationService
{
    protected ?string $apiKey;
    protected ?string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.whatsapp.api_key');
        $this->endpoint = config('services.whatsapp.endpoint');
    }

    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): bool
    {
        $phone = $this->normalizePhoneNumber($recipientPhone);

        if (empty($this->endpoint) || empty($this->apiKey)) {
            Log::info("[WhatsApp Notification - Dry Run] To: {$phone} | Message: {$message}");
            return true;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, [
                    'to' => $phone,
                    'message' => $message,
                    'metadata' => $metadata,
                ]);

            WebhookLog::create([
                'provider' => 'whatsapp',
                'event' => 'outbound_message',
                'payload' => ['to' => $phone, 'length' => strlen($message)],
                'status' => $response->successful() ? 'sent' : 'failed',
                'response' => $response->body(),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("[WhatsApp Error] Failed to send to {$phone}: " . $e->getMessage());

            WebhookLog::create([
                'provider' => 'whatsapp',
                'event' => 'outbound_message_error',
                'payload' => ['to' => $phone, 'error' => $e->getMessage()],
                'status' => 'error',
                'response' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function normalizePhoneNumber(string $rawNumber): string
    {
        // Strip everything except digits
        $clean = preg_replace('/[^0-9]/', '', $rawNumber);

        // Convert Indonesian local format 08... to 628...
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }

        return $clean;
    }
}
