<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailNotificationService
{
    /**
     * Dynamically configure SMTP mailer from database settings or fallback to .env/config.
     */
    public function configureMailer(): void
    {
        $settings = Setting::getAll();

        $mailer      = $settings['mail_mailer'] ?? config('mail.default', 'smtp');
        $host        = $settings['mail_host'] ?? config('mail.mailers.smtp.host', 'smtp.gmail.com');
        $port        = $settings['mail_port'] ?? config('mail.mailers.smtp.port', '587');
        $username    = $settings['mail_username'] ?? config('mail.mailers.smtp.username', '');
        $password    = $settings['mail_password'] ?? config('mail.mailers.smtp.password', '');
        $encryption  = $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls');
        $fromAddress = $settings['mail_from_address'] ?? config('mail.from.address', 'no-reply@simpan.site');
        $fromName    = $settings['mail_from_name'] ?? config('mail.from.name', 'MyStorage Cloud');

        if ($encryption === 'none' || empty($encryption)) {
            $encryption = null;
        }

        Config::set('mail.default', $mailer);
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);
    }

    /**
     * Send Base HTML Email wrapper.
     */
    public function sendHtmlEmail(string $toEmail, string $toName, string $subject, string $htmlContent): bool
    {
        try {
            $this->configureMailer();

            $fromAddress = Setting::get('mail_from_address', config('mail.from.address', 'no-reply@simpan.site'));
            $fromName    = Setting::get('mail_from_name', config('mail.from.name', 'MyStorage Cloud'));

            Mail::send([], [], function ($message) use ($toEmail, $toName, $fromAddress, $fromName, $subject, $htmlContent) {
                $message->to($toEmail, $toName)
                    ->from($fromAddress, $fromName)
                    ->subject($subject)
                    ->html($htmlContent);
            });

            Log::info("Mail successfully dispatched to {$toEmail} - Subject: {$subject}");
            return true;
        } catch (\Throwable $e) {
            Log::error("Mail dispatch failure to {$toEmail}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate responsive modern HTML email layout with MyStorage theme.
     */
    public function buildLayout(string $title, string $badge, string $bodyContent, string $status = 'success'): string
    {
        $headerGradient = match ($status) {
            'warning' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%)',
            'error'   => 'linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #b91c1c 100%)',
            default   => 'linear-gradient(135deg, #2563eb 0%, #4f46e5 50%, #7c3aed 100%)',
        };

        $badgeBg = match ($status) {
            'warning' => 'rgba(245, 158, 11, 0.2)',
            'error'   => 'rgba(239, 68, 68, 0.2)',
            default   => 'rgba(255, 255, 255, 0.2)',
        };

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0b0f19; margin: 0; padding: 28px 12px; color: #1e293b; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05); }
        .header { background: {$headerGradient}; padding: 36px 30px; text-align: center; color: #ffffff; position: relative; }
        .brand-pill { display: inline-flex; align-items: center; gap: 6px; background: {$badgeBg}; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); padding: 5px 14px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 12px; border: 1px solid rgba(255, 255, 255, 0.3); text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        .header h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; text-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .header p { margin: 6px 0 0; font-size: 13px; opacity: 0.95; font-weight: 500; }
        .content { padding: 32px 28px 24px; line-height: 1.65; font-size: 14px; color: #334155; }
        .highlight-box { background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 18px; padding: 20px 22px; margin: 20px 0; box-shadow: 0 4px 12px rgba(37,99,235,0.05); }
        .card-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.06); font-size: 13px; }
        .card-row:last-child { border-bottom: none; padding-bottom: 0; }
        .button { display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: #ffffff !important; padding: 14px 30px; border-radius: 14px; text-decoration: none; font-weight: 700; font-size: 14px; letter-spacing: 0.2px; text-align: center; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4); margin: 18px 0; }
        .footer { padding: 24px 30px; text-align: center; font-size: 11px; color: #64748b; background: #0f172a; border-top: 1px solid #1e293b; line-height: 1.7; }
        .footer a { color: #38bdf8; text-decoration: none; font-weight: 700; }
        .security-badge { display: inline-block; background: rgba(255,255,255,0.06); padding: 3px 10px; border-radius: 6px; font-size: 10px; color: #94a3b8; font-weight: 600; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.1); }
        .success-card { background: #ecfdf5; border-left: 5px solid #10b981; padding: 14px 18px; border-radius: 14px; font-size: 13px; color: #065f46; margin: 18px 0; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="brand-pill">☁️ MYSTORAGE CLOUD • {$badge}</div>
            <h1>{$title}</h1>
            <p>Private Cloud Storage & Collaborative Workspace</p>
        </div>
        <div class="content">
            {$bodyContent}
        </div>
        <div class="footer">
            <div class="security-badge">🔒 Terenkripsi & Dilindungi Sistem Keamanan MyStorage</div><br>
            © 2026 <strong>MyStorage Cloud</strong> • Layanan Penyimpanan Cloud Mandiri Terpercaya.<br>
            Pesan otomatis, mohon tidak membalas email ini secara langsung.
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send Test Email for Admin SMTP validation (following ig-unfollow-agent architecture).
     */
    public function sendTestMail(string $targetEmail): array
    {
        $host = Setting::get('mail_host', config('mail.mailers.smtp.host', 'smtp.gmail.com'));
        $port = Setting::get('mail_port', config('mail.mailers.smtp.port', '587'));
        $from = Setting::get('mail_from_address', config('mail.from.address', 'no-reply@simpan.site'));
        $time = now()->format('d M Y, H:i:s') . ' WIB';

        $subject = "🧪 Uji Coba Konfigurasi SMTP Mail Server MyStorage Berhasil";
        $body = <<<HTML
            <p>Halo <strong>Administrator</strong>,</p>
            <p>Selamat! Konfigurasi SMTP Mail Gateway server email Anda di <strong>MyStorage Cloud</strong> telah terhubung dan berfungsi dengan 100% sempurna.</p>
            
            <div class="success-card">
                <strong>✅ Status: SMTP Mail Server Terhubung & Aktif.</strong>
            </div>

            <div class="highlight-box">
                <div style="font-size: 11px; color: #0284c7; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">Rincian Koneksi Pengujian:</div>
                <div class="card-row">
                    <span style="color: #64748b;">SMTP Host:</span>
                    <strong style="color: #0f172a; font-family: monospace;">{$host}</strong>
                </div>
                <div class="card-row">
                    <span style="color: #64748b;">SMTP Port:</span>
                    <strong style="color: #0f172a;">{$port}</strong>
                </div>
                <div class="card-row">
                    <span style="color: #64748b;">From Address:</span>
                    <strong style="color: #0f172a;">{$from}</strong>
                </div>
                <div class="card-row">
                    <span style="color: #64748b;">Waktu Pengujian:</span>
                    <strong style="color: #0f172a;">{$time}</strong>
                </div>
            </div>

            <p style="font-size: 13px; color: #475569;">
                Dengan konfigurasi ini, server MyStorage siap mengirimkan email verifikasi pendaftaran akun, reset password, dan notifikasi keamanan kepada seluruh pengguna.
            </p>
HTML;

        try {
            $this->configureMailer();

            $fromAddress = Setting::get('mail_from_address', config('mail.from.address', 'no-reply@simpan.site'));
            $fromName    = Setting::get('mail_from_name', config('mail.from.name', 'MyStorage Cloud'));
            $html = $this->buildLayout("Uji Coba SMTP Berhasil", "TEST MAIL", $body, 'success');

            Mail::send([], [], function ($message) use ($targetEmail, $fromAddress, $fromName, $subject, $html) {
                $message->to($targetEmail, "Administrator")
                    ->from($fromAddress, $fromName)
                    ->subject($subject)
                    ->html($html);
            });

            return [
                'status'  => 'success',
                'message' => "Email uji coba berhasil dikirim ke {$targetEmail}. Silakan periksa kotak masuk atau folder spam Anda.",
            ];
        } catch (\Throwable $e) {
            Log::error("SMTP test mail failed: " . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => "Gagal mengirim email uji coba: " . $e->getMessage(),
            ];
        }
    }
}
