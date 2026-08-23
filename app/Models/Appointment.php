<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_CONFIRMED = 'Confirmed';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** Clinic working hours: 09:00 AM - 05:00 PM in 30-minute slots. */
    public const SLOTS = [
        '09:00', '09:30', '10:00', '10:30',
        '11:00', '11:30', '13:00', '13:30',
        '14:00', '14:30', '15:00', '15:30',
        '16:00', '16:30',
    ];

    /** Common dental services offered by the clinic. */
    public const SERVICES = [
        'General Checkup',
        'Teeth Cleaning (Prophylaxis)',
        'Tooth Extraction',
        'Filling / Restoration',
        'Root Canal Treatment',
        'Dental Crown',
        'Braces / Orthodontic Consultation',
        'Teeth Whitening',
    ];

    /**
     * Double-booking guard: is the given date + time slot already occupied?
     *
     * A slot is taken when there is any non-cancelled appointment for the same
     * date and slot, by the same dentist OR unassigned (null dentist). If no
     * dentist is provided, the slot is taken if ANY appointment exists for it.
     */
    public static function isSlotTaken(?int $dentistId, string $date, string $slot, ?int $ignoreAppointmentId = null): bool
    {
        $query = static::query()
            ->whereDate('appointment_date', $date)
            ->where('time_slot', $slot)
            ->where('status', '!=', self::STATUS_CANCELLED);

        if ($dentistId !== null) {
            $query->where(fn ($q) => $q->where('dentist_id', $dentistId)->orWhereNull('dentist_id'));
        }

        if ($ignoreAppointmentId !== null) {
            $query->where('id', '!=', $ignoreAppointmentId);
        }

        return $query->exists();
    }

    /** List of slots still free for a dentist on a given date. */
    public static function freeSlots(?int $dentistId, string $date): array
    {
        $taken = static::query()
            ->whereDate('appointment_date', $date)
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->when($dentistId !== null, fn ($q) => $q->where(fn ($q2) => $q2->where('dentist_id', $dentistId)->orWhereNull('dentist_id')))
            ->pluck('time_slot')
            ->all();

        $free = array_values(array_diff(self::SLOTS, $taken));

        // Past slots today are no longer bookable in real time.
        if ($date === now()->toDateString()) {
            $free = array_values(array_filter($free, fn ($slot) => $slot > now()->format('H:i')));
        }

        return $free;
    }

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'appointment_date',
        'time_slot',
        'service_type',
        'status',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }

    public function treatmentRecord(): HasOne
    {
        return $this->hasOne(TreatmentRecord::class, 'appointment_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'appointment_id');
    }

    /** Formatted slot, e.g. "09:00" -> "09:00 AM". */
    public function getFormattedSlotAttribute(): string
    {
        return Carbon::createFromFormat('H:i', $this->time_slot)->format('h:i A');
    }
}
