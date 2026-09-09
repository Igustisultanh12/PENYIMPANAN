<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = url("/verify-email/{$this->token}");

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Anda - MyStorage')
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Terima kasih telah mendaftar di MyStorage. Untuk mulai mengamankan dan mengelola berkas Anda di cloud storage mandiri, silakan konfirmasi email Anda dengan mengklik tombol di bawah.')
            ->action('Verifikasi Email Saya', $verificationUrl)
            ->line('Tautan verifikasi ini akan kedaluwarsa dalam 24 jam demi keamanan akun Anda.')
            ->line('Jika Anda tidak merasa mendaftar di MyStorage, abaikan pesan ini.')
            ->salutation('Salam hormat, Tim MyStorage');
    }
}
