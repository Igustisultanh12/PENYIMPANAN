<?php

namespace App\Services\Notifications;

interface WhatsAppNotificationService
{
    /**
     * Send a WhatsApp message to a normalized recipient phone number.
     *
     * @param string $recipientPhone Normalized E.164 phone number (e.g., +628123456789)
     * @param string $message Text content of the notification
     * @param array<string, mixed> $metadata Optional extra payload (template, buttons, media)
     * @return bool
     */
    public function sendMessage(string $recipientPhone, string $message, array $metadata = []): bool;

    /**
     * Normalize a raw phone number into standard international format (e.g., 0812... -> 62812...).
     */
    public function normalizePhoneNumber(string $rawNumber): string;
}
