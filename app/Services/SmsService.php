<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS notification service.
 *
 * Mirrors the eTextMo HTTP API structure:
 *   POST {SMS_ENDPOINT}
 *   form: api_key, sender_id, recipients, message
 *
 * When SMS_MOCK=true (default) the HTTP call is simulated and the attempt is
 * recorded in sms_logs with status "Mocked" so the feature can be demonstrated
 * without a real SMS gateway / network access.
 */
class SmsService
{
    public function __construct(
        protected bool $mock = true,
        protected string $apiKey = '',
        protected string $senderId = '',
        protected string $endpoint = '',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            mock: (bool) config('services.sms.mock', true),
            apiKey: (string) config('services.sms.api_key', ''),
            senderId: (string) config('services.sms.sender_id', ''),
            endpoint: (string) config('services.sms.endpoint', 'https://api.etextmo.com/api/send-message'),
        );
    }

    /**
     * Send a message to one recipient and persist the attempt in sms_logs.
     */
    public function send(string $recipientPhone, string $message): SmsLog
    {
        if ($this->mock) {
            // Simulate the gateway round-trip.
            $log = SmsLog::create([
                'recipient_phone' => $recipientPhone,
                'message' => $message,
                'status' => SmsLog::STATUS_MOCKED,
                'sent_at' => now(),
            ]);

            Log::info('[SMS][MOCK] SMS queued for delivery.', [
                'to' => $recipientPhone,
                'message' => $message,
            ]);

            return $log;
        }

        try {
            $response = Http::asForm()->timeout(15)->post($this->endpoint, [
                'api_key' => $this->apiKey,
                'sender_id' => $this->senderId,
                'recipients' => $recipientPhone,
                'message' => $message,
            ]);

            $status = $response->successful() ? SmsLog::STATUS_SENT : SmsLog::STATUS_FAILED;

            if (! $response->successful()) {
                Log::warning('[SMS] Gateway returned a non-success response.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $status = SmsLog::STATUS_FAILED;
            Log::error('[SMS] Failed to send SMS: '.$e->getMessage(), ['to' => $recipientPhone]);
        }

        return SmsLog::create([
            'recipient_phone' => $recipientPhone,
            'message' => $message,
            'status' => $status,
            'sent_at' => now(),
        ]);
    }

    /**
     * Send an appointment confirmation reminder to the patient.
     * Returns the created sms_log entry, or null if the patient has no contact number.
     */
    public function sendAppointmentConfirmation(Appointment $appointment): ?SmsLog
    {
        $phone = $appointment->patient?->contact_no;

        if (empty($phone)) {
            Log::info('[SMS] Skipped reminder: patient has no contact number.', [
                'appointment_id' => $appointment->id,
            ]);

            return null;
        }

        $date = $appointment->appointment_date?->format('M d, Y') ?? $appointment->appointment_date;
        $slot = $appointment->formatted_slot;

        $message = sprintf(
            'Good day, %s! Your appointment at %s on %s for %s has been CONFIRMED. Please arrive 15 minutes early. - Dental Clinic',
            $appointment->patient->name,
            $slot,
            $date,
            $appointment->service_type
        );

        return $this->send($phone, $message);
    }
}
