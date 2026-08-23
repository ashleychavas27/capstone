<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Clinic chat bot service.
 *
 * Primary path: Google Gemini API (gemini-2.5-flash) with a clinic-specific
 * system instruction so answers stay grounded in real clinic information.
 *
 * Fallback path: a small rule-based knowledge base so the chat bot keeps
 * working during demonstrations even when there is no internet access or
 * the API key is not configured.
 */
class ChatBotService
{
    protected const MODEL = 'gemini-2.5-flash';

    protected const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':generateContent';

    /** Clinic knowledge used for both the Gemini system prompt and the offline fallback. */
    protected const CLINIC_CONTEXT = <<<'TXT'
    You are the friendly online assistant of a Dental Clinic.
    Answer questions briefly (2-4 sentences), in English or Taglish, and stay on dental topics.

    Clinic facts you may share:
    - Services: General Checkup, Teeth Cleaning (Prophylaxis), Tooth Extraction,
      Filling / Restoration, Root Canal Treatment, Dental Crown,
      Braces / Orthodontic Consultation, Teeth Whitening.
    - Clinic hours: Monday to Saturday, 9:00 AM to 5:00 PM (lunch break 12:00 PM to 1:00 PM). Closed on Sundays.
    - Appointments: patients can book online through the "Book Appointment" page.
      Slots are 30 minutes each and show real-time availability.
    - Booking requires an account; after booking the status is "Pending" until the secretary confirms it.
    - Patients receive an SMS notification once their appointment is confirmed.
    - You cannot confirm, cancel or reschedule appointments yourself; advise the user
      to contact the clinic secretary for those requests.
    - For emergencies, advise the patient to call or visit the clinic directly.
    TXT;

    public function reply(string $message): string
    {
        $apiKey = (string) config('services.gemini.api_key', '');

        if ($apiKey !== '' && $apiKey !== 'your-gemini-api-key') {
            try {
                return $this->askGemini($apiKey, $message);
            } catch (\Throwable $e) {
                Log::warning('[CHATBOT] Gemini request failed, using offline fallback.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->fallbackReply($message);
    }

    /** Call the Gemini generateContent REST endpoint. */
    protected function askGemini(string $apiKey, string $message): string
    {
        $response = Http::timeout(20)->post(self::ENDPOINT.'?key='.$apiKey, [
            'systemInstruction' => [
                'parts' => [['text' => self::CLINIC_CONTEXT]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $message]]],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 300,
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API error '.$response->status().': '.$response->body());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        return trim($text);
    }

    /** Rule-based offline answers (keyword matching). */
    protected function fallbackReply(string $message): string
    {
        $text = strtolower($message);

        $rules = [
            'hours|open|schedule of clinic|clinic time' => 'Our clinic is open Monday to Saturday, 9:00 AM to 5:00 PM, with a lunch break from 12:00 PM to 1:00 PM. We are closed on Sundays.',
            'service|cleaning|prophylaxis|extract|filling|root canal|crown|braces|whiten|whitening|checkup' => 'We offer General Checkup, Teeth Cleaning (Prophylaxis), Tooth Extraction, Filling/Restoration, Root Canal Treatment, Dental Crown, Braces/Orthodontic Consultation, and Teeth Whitening.',
            'book|appointment|reserve|slot' => 'You can book an appointment through the "Book Appointment" page. Slots are 30 minutes each and availability updates in real time. The secretary will confirm your booking and you will receive an SMS.',
            'cancel|resched|move my|change my' => 'For cancellations or rescheduling, please contact the clinic secretary so they can update your appointment right away.',
            'price|cost|how much|bayad|presyo' => 'Service fees depend on the treatment required. Please visit or message the clinic so we can give you an accurate quotation after a checkup.',
            'location|address|where|saan' => 'You can find our clinic address on the About section of this system. Please call ahead so we can reserve your slot.',
            'emergency|masakit ang|toothache|sumasakit' => 'For severe pain or dental emergencies, please go directly to the clinic or call us so we can attend to you as soon as possible.',
            'hi|hello|hey|kumusta|kamusta|good morning|good afternoon' => 'Hello! Welcome to the Dental Clinic. I can help you with our services, clinic hours, and how to book an appointment. How may I help you?',
        ];

        foreach ($rules as $pattern => $answer) {
            if (preg_match('/'.$pattern.'/', $text)) {
                return $answer;
            }
        }

        return 'Sorry, I could not process that right now. You can ask me about our services, clinic hours, or how to book an appointment — or leave your concern with the clinic secretary.';
    }
}
