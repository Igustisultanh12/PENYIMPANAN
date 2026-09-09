<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService implements WhatsAppNotificationService
{
    /**
     * Normalize phone number to international format (e.g., 0812... -> 62812...).
     */
    public function normalizePhoneNumber(string $rawNumber): string
    {
        // Strip non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $rawNumber);

        // Convert Indonesian local format 08... to 628...
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Static alias for phone normalization.
     */
    public static function normalizePhone(string $rawNumber): string
    {
        $phone = preg_replace('/[^0-9]/', '', $rawNumber);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Get the configured gateway base URL.
     */
    public function getGatewayUrl(): string
    {
        $url = Setting::get('wa_gateway_url', config('services.whatsapp.url', 'http://127.0.0.1:3000'));
        return rtrim($url ?: 'http://127.0.0.1:3000', '/');
    }

    /**
     * Send message (implements WhatsAppNotificationService).
     */
    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): bool
    {
        $res = $this->send($recipientPhone, $message);
        return (bool) ($res['status'] ?? false);
    }

    /**
     * Send message conforming to kantor architecture (via Node.js Port 3000 GET /send).
     *
     * @param string $target
     * @param string $message
     * @return array{status: bool, message?: string, data?: mixed}
     */
    public function send(string $target, string $message): array
    {
        // Check Master Toggle
        $enabled = Setting::get('wa_notifications_enabled', '1');
        if ($enabled === '0' || $enabled === false || $enabled === 0) {
            Log::info("WhatsappService: Notifikasi WA diblokir oleh Admin (Status Master: NONAKTIF). Target: {$target}");
            return [
                'status' => false,
                'message' => 'Notifikasi WhatsApp dinonaktifkan oleh Administrator.',
            ];
        }

        if (empty($target)) {
            Log::warning("WhatsappService: Target phone number is empty.");
            return [
                'status' => false,
                'message' => 'Nomor WhatsApp tujuan kosong.',
            ];
        }

        $phone = $this->normalizePhoneNumber($target);
        $baseUrl = $this->getGatewayUrl();

        try {
            $response = Http::timeout(10)->get("{$baseUrl}/send", [
                'number' => $phone,
                'msg'    => $message,
            ]);

            $isSuccess = $response->successful();

            WebhookLog::create([
                'provider' => 'whatsapp',
                'event'    => 'outbound_message',
                'payload'  => ['to' => $phone, 'length' => strlen($message)],
                'status'   => $isSuccess ? 'sent' : 'failed',
                'response' => $response->body(),
            ]);

            if ($isSuccess) {
                Log::info("WA Terkirim ke: {$phone}");
                return [
                    'status' => true,
                    'message' => 'Pesan WhatsApp berhasil dikirim.',
                    'data'   => $response->json(),
                ];
            }

            Log::error("Server WA Port 3000 merespon gagal: " . $response->body());
            return [
                'status'  => false,
                'message' => 'Server Gateway WhatsApp merespon gagal: ' . $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error("Koneksi ke Server WA Gagal: " . $e->getMessage());

            WebhookLog::create([
                'provider' => 'whatsapp',
                'event'    => 'outbound_message_error',
                'payload'  => ['to' => $phone, 'error' => $e->getMessage()],
                'status'   => 'error',
                'response' => $e->getMessage(),
            ]);

            return [
                'status'  => false,
                'message' => 'Koneksi ke Gateway WhatsApp gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Query gateway live status (GET /status-wa).
     */
    public function getGatewayStatus(): array
    {
        $baseUrl = $this->getGatewayUrl();

        try {
            $response = Http::timeout(5)->get("{$baseUrl}/status-wa");
            if (!$response->successful()) {
                $response = Http::timeout(5)->get("{$baseUrl}/status");
            }

            if ($response->successful()) {
                $data = $response->json();
                $rawStatus = strtoupper($data['status'] ?? 'ONLINE');
                $isOnline = $rawStatus === 'ONLINE' || ($data['connected'] ?? false) === true;

                return [
                    'status' => $isOnline ? 'ONLINE' : ($rawStatus === 'WAITING_PAIR' ? 'WAITING_PAIR' : $rawStatus),
                    'connected' => $isOnline,
                    'pairing_code' => $data['pairing_code'] ?? null,
                    'details' => $data,
                ];
            }

            return [
                'status' => 'ERROR',
                'connected' => false,
                'pairing_code' => null,
                'message' => 'Gateway merespon HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'OFFLINE',
                'connected' => false,
                'qr' => null,
                'message' => 'Tidak dapat terhubung ke ' . $baseUrl . ' (' . $e->getMessage() . ')',
            ];
        }
    }

    /**
     * Send test WhatsApp message for Admin validation.
     */
    public function sendTestMessage(string $targetPhone, string $customMessage = ''): array
    {
        $message = $customMessage ?: "🧪 *UJI COBA GATEWAY WHATSAPP MYSTORAGE*\n\nHalo Administrator!\nKoneksi Gateway WhatsApp server Anda berhasil terhubung dan berfungsi dengan 100% normal.\n\n_Waktu: " . now()->format('d M Y H:i:s') . " WIB_";

        return $this->send($targetPhone, $message);
    }

    /**
     * Reset WhatsApp gateway session (logout & trigger fresh QR code generation).
     */
    public function resetSession(): array
    {
        $baseUrl = $this->getGatewayUrl();

        try {
            $response = Http::timeout(10)->post("{$baseUrl}/reset-session");
            if (!$response->successful()) {
                $response = Http::timeout(10)->get("{$baseUrl}/reset-session");
            }

            return [
                'status' => $response->successful(),
                'message' => $response->json('message') ?? 'Sesi WhatsApp berhasil di-reset.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Gagal menghubungi gateway: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Request WhatsApp Pairing Code for the provided phone number.
     */
    public function requestPairingCode(string $phoneNumber): array
    {
        $baseUrl = $this->getGatewayUrl();
        $cleanPhone = $this->normalizePhoneNumber($phoneNumber);

        try {
            $response = Http::timeout(15)->post("{$baseUrl}/pairing-code", [
                'number' => $cleanPhone,
            ]);

            if ($response->successful()) {
                return [
                    'status' => true,
                    'pairing_code' => $response->json('pairing_code'),
                    'message' => $response->json('message') ?? 'Kode pairing berhasil dibuat.',
                ];
            }

            return [
                'status' => false,
                'message' => $response->json('message') ?? 'Gagal membuat kode pairing dari gateway.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Gagal menghubungi gateway: ' . $e->getMessage(),
            ];
        }
    }
}
