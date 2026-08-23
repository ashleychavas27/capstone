<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'sms:send-reminders';

    protected $description = 'Send day-before SMS reminders for confirmed appointments scheduled tomorrow';

    public function handle(SmsService $sms): int
    {
        // Appointments happening tomorrow (Asia/Manila timezone per app config).
        $appointments = Appointment::with(['patient', 'dentist'])
            ->whereDate('appointment_date', now()->addDay()->toDateString())
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No confirmed appointments found for tomorrow.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($appointments as $appointment) {
            $log = $sms->sendDayBeforeReminder($appointment);

            if ($log !== null) {
                $sent++;
                $this->line(sprintf(
                    '[%s] Reminder %s -> %s (%s, %s)',
                    $log->status,
                    $log->recipient_phone,
                    $appointment->patient?->name,
                    $appointment->appointment_date->format('M d, Y'),
                    $appointment->formatted_slot
                ));
            }
        }

        $this->info("Done. {$sent} reminder(s) processed out of {$appointments->count()} appointment(s).");

        return self::SUCCESS;
    }
}
