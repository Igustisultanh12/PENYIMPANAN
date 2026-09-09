<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Notifications\MailNotificationService;
use App\Services\Notifications\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    /**
     * Check if authenticated user has admin privileges.
     */
    protected function authorizeAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Halaman dan aksi ini hanya dapat diakses oleh Administrator.',
            ], 403);
        }
        return null;
    }

    /**
     * Retrieve all administrative settings (WhatsApp & SMTP Mail).
     */
    public function getSettings(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        return response()->json([
            'success' => true,
            'data' => [
                // WhatsApp Gateway Settings
                'wa_notifications_enabled' => Setting::get('wa_notifications_enabled', '1'),
                'wa_gateway_url'           => Setting::get('wa_gateway_url', 'http://127.0.0.1:3000'),

                // SMTP Mail Settings (conforming to ig-unfollow-agent architecture)
                'mail_mailer'       => Setting::get('mail_mailer', config('mail.default', 'smtp')),
                'mail_host'         => Setting::get('mail_host', config('mail.mailers.smtp.host', 'smtp.gmail.com')),
                'mail_port'         => (string) Setting::get('mail_port', config('mail.mailers.smtp.port', '587')),
                'mail_username'     => Setting::get('mail_username', config('mail.mailers.smtp.username', '')),
                'mail_password'     => Setting::get('mail_password', config('mail.mailers.smtp.password', '')),
                'mail_encryption'   => Setting::get('mail_encryption', config('mail.mailers.smtp.encryption', 'tls')),
                'mail_from_address' => Setting::get('mail_from_address', config('mail.from.address', 'no-reply@simpan.site')),
                'mail_from_name'    => Setting::get('mail_from_name', config('mail.from.name', 'MyStorage Cloud')),
            ],
        ]);
    }

    /**
     * Update administrative settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $validated = $request->validate([
            'wa_notifications_enabled' => ['nullable', 'in:0,1'],
            'wa_gateway_url'           => ['nullable', 'string', 'max:255'],
            'mail_mailer'              => ['nullable', 'string', 'max:50'],
            'mail_host'                => ['nullable', 'string', 'max:255'],
            'mail_port'                => ['nullable', 'numeric'],
            'mail_username'            => ['nullable', 'string', 'max:255'],
            'mail_password'            => ['nullable', 'string', 'max:255'],
            'mail_encryption'          => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_from_address'        => ['nullable', 'string', 'email', 'max:255'],
            'mail_from_name'           => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, is_null($value) ? '' : (string) $value);
        }

        // Apply updated mail settings immediately
        try {
            app(MailNotificationService::class)->configureMailer();
        } catch (\Throwable $e) {
            // Ignore runtime failure on reconfigure
        }

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'admin.settings_update',
            'target_type' => 'Setting',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => array_keys($validated),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan WhatsApp Gateway & SMTP Email berhasil disimpan.',
        ]);
    }

    /**
     * Get live status of the WhatsApp Gateway.
     */
    public function getWhatsAppStatus(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $status = app(WhatsappService::class)->getGatewayStatus();

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }

    /**
     * Send a test WhatsApp message.
     */
    public function testWhatsApp(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $request->validate([
            'target_phone' => ['required', 'string', 'min:8', 'max:30'],
            'message'      => ['nullable', 'string', 'max:1000'],
        ]);

        $res = app(WhatsappService::class)->sendTestMessage(
            $request->input('target_phone'),
            $request->input('message') ?: ''
        );

        return response()->json([
            'success' => $res['status'] ?? false,
            'message' => $res['message'] ?? ($res['status'] ? 'Pesan WhatsApp berhasil dikirim.' : 'Gagal mengirim pesan WhatsApp.'),
            'data'    => $res['data'] ?? null,
        ], ($res['status'] ?? false) ? 200 : 422);
    }

    /**
     * Send a test SMTP Email.
     */
    public function testMail(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $res = app(MailNotificationService::class)->sendTestMail($request->input('test_email'));

        $isSuccess = ($res['status'] ?? '') === 'success';

        return response()->json([
            'success' => $isSuccess,
            'message' => $res['message'] ?? ($isSuccess ? 'Email uji coba berhasil dikirim.' : 'Gagal mengirim email uji coba.'),
        ], $isSuccess ? 200 : 422);
    }

    /**
     * Reset WhatsApp session (trigger fresh QR code).
     */
    public function resetWhatsAppSession(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeAdmin($request)) {
            return $deny;
        }

        $res = app(WhatsappService::class)->resetSession();

        return response()->json([
            'success' => $res['status'] ?? false,
            'message' => $res['message'] ?? 'Permintaan reset sesi WhatsApp berhasil dikirim.',
        ]);
    }
}
