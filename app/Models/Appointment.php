<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

    /*
    |--------------------------------------------------------------------------
    | Clinic schedule
    |--------------------------------------------------------------------------
    |
    | Hours, lunch break, procedures (with estimated duration) and dentist duty
    | days all come from config/clinic.php so the clinic's own schedule is the
    | single source of truth for what can be booked.
    |
    */

    /** Opening hours [open, close] for a date, or null when the clinic is closed. */
    public static function hoursFor(string|Carbon|\DateTimeInterface $date): ?array
    {
        $weekday = (int) Carbon::parse($date)->isoWeekday();

        return config("clinic.hours.{$weekday}");
    }

    /** Is the clinic open on this date at all? (Sundays are closed.) */
    public static function isOpenOn(string|Carbon|\DateTimeInterface $date): bool
    {
        return self::hoursFor($date) !== null;
    }

    /**
     * Every slot start offered on an open day: a 30-minute lattice inside
     * opening hours that never runs into the lunch break.
     *
     * @return array<int, string>
     */
    public static function slots(): array
    {
        $step = (int) config('clinic.slot_minutes', 30);
        $hours = config('clinic.hours.1') ?? ['09:00', '16:00'];
        $slots = [];

        for ($minute = self::toMinutes($hours[0]); $minute < self::toMinutes($hours[1]); $minute += $step) {
            $start = self::toClock($minute);

            if (self::fitsOpeningHours($start, $step)) {
                $slots[] = $start;
            }
        }

        return $slots;
    }

    /**
     * Everything the clinic can schedule: the client's procedure list first
     * (with estimated durations), then the other services the clinic offers.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function procedures(): array
    {
        return array_merge(
            config('clinic.procedures', []),
            config('clinic.services', [])
        );
    }

    /**
     * Procedures that must be arranged by the clinic rather than booked online:
     * no estimated duration and not offered for self-booking.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function byAppointmentProcedures(): array
    {
        return array_values(array_filter(
            self::procedures(),
            fn (array $procedure) => ($procedure['online'] ?? false) === false
        ));
    }

    /**
     * Procedures a patient may book online. Procedures marked "by appointment"
     * are scheduled by the clinic instead.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function onlineProcedures(): array
    {
        return array_values(array_filter(
            self::procedures(),
            fn (array $procedure) => ($procedure['online'] ?? false) === true
        ));
    }

    /** Procedure names, in configured order. */
    public static function procedureNames(): array
    {
        return array_values(array_map(fn (array $p) => $p['name'], self::procedures()));
    }

    /** A single procedure definition by name, or null when it is not offered. */
    public static function procedure(?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        foreach (self::procedures() as $procedure) {
            if ($procedure['name'] === $name) {
                return $procedure;
            }
        }

        return null;
    }

    /**
     * Minutes reserved for a procedure. Historical rows and free-text services
     * fall back to `clinic.default_duration`.
     */
    public static function durationFor(?string $service): int
    {
        return (int) (self::procedure($service)['minutes'] ?? config('clinic.default_duration', 30));
    }

    /** Estimated-duration label for a procedure, e.g. "1 – 1.5 hours". */
    public static function durationLabel(?string $service): ?string
    {
        return self::procedure($service)['display'] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Dentist duty schedule
    |--------------------------------------------------------------------------
    */

    /**
     * ISO weekdays a dentist is on duty. Dentists missing from the config
     * default to every open day, so a newly created account is never blocked.
     *
     * @return array<int, int>
     */
    public static function dutyDaysFor(?User $dentist): array
    {
        return $dentist?->dutyDays() ?? config('clinic.default_duty_days', [1, 2, 3, 4, 5, 6]);
    }

    /** Is this dentist on duty on the given date? Null dentist = unassigned, so no restriction. */
    public static function isDentistOnDuty(?int $dentistId, string|Carbon|\DateTimeInterface $date): bool
    {
        if ($dentistId === null) {
            return true;
        }

        $dentist = User::find($dentistId);

        if ($dentist === null) {
            return true;
        }

        return $dentist->isOnDutyOn($date);
    }

    /** Human-readable duty days, e.g. "Mon, Tue, Wed, Fri". */
    public static function dutyDayLabel(?User $dentist): string
    {
        if ($dentist !== null) {
            return $dentist->dutyDayLabel();
        }

        return implode(', ', array_map(
            fn (int $day) => self::weekdayName($day, true),
            self::dutyDaysFor(null)
        ));
    }

    /** Weekday name, e.g. 3 -> "Wednesday" (or "Wed" when short). */
    public static function weekdayName(int $isoWeekday, bool $short = false): string
    {
        $names = [
            1 => ['Monday', 'Mon'], 2 => ['Tuesday', 'Tue'], 3 => ['Wednesday', 'Wed'],
            4 => ['Thursday', 'Thu'], 5 => ['Friday', 'Fri'], 6 => ['Saturday', 'Sat'], 7 => ['Sunday', 'Sun'],
        ];

        return $names[$isoWeekday][$short ? 1 : 0] ?? '';
    }

    /** Opening hours as "9:00 AM – 4:00 PM". */
    public static function hoursLabel(?array $hours = null): string
    {
        $hours ??= config('clinic.hours.1') ?? ['09:00', '16:00'];

        return self::timeLabel($hours[0]).' – '.self::timeLabel($hours[1]);
    }

    /** Days the clinic is open, e.g. "Monday to Saturday". */
    public static function openDaysLabel(): string
    {
        $open = array_values(array_filter(range(1, 7), fn (int $day) => config("clinic.hours.{$day}") !== null));

        if ($open === [1, 2, 3, 4, 5, 6]) {
            return 'Monday to Saturday';
        }

        return implode(', ', array_map(fn (int $day) => self::weekdayName($day), $open));
    }

    /** Lunch break as "12:00 PM to 1:00 PM", or null when none is configured. */
    public static function lunchLabel(): ?string
    {
        $lunch = config('clinic.lunch');

        return $lunch ? self::timeLabel($lunch[0]).' to '.self::timeLabel($lunch[1]) : null;
    }

    /** "09:00" -> "9:00 AM" */
    public static function timeLabel(string $clock): string
    {
        return Carbon::createFromFormat('H:i', $clock)->format('g:i A');
    }

    /** Minutes as "1 hour 30 minutes" / "30 minutes". */
    public static function minutesLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' hour'.($hours > 1 ? 's' : '');
        }

        if ($rest > 0) {
            $parts[] = $rest.' minute'.($rest > 1 ? 's' : '');
        }

        return implode(' ', $parts) ?: '0 minutes';
    }

    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */

    /**
     * Does [start, start + duration) sit inside opening hours and clear the
     * lunch break?
     */
    public static function fitsOpeningHours(string $start, int $minutes, ?string $date = null): bool
    {
        $hours = self::hoursFor($date ?? now()->toDateString());

        if ($hours === null) {
            return false;
        }

        $startMinute = self::toMinutes($start);
        $endMinute = $startMinute + $minutes;
        $openMinute = self::toMinutes($hours[0]);
        $closeMinute = self::toMinutes($hours[1]);

        if ($startMinute < $openMinute || $endMinute > $closeMinute) {
            return false;
        }

        $lunch = config('clinic.lunch');

        if ($lunch && $startMinute < self::toMinutes($lunch[1]) && $endMinute > self::toMinutes($lunch[0])) {
            return false;
        }

        return true;
    }

    /**
     * Non-cancelled appointments for a date, as [startMinute, endMinute] ranges.
     *
     * A range with a null dentist blocks everyone (unassigned bookings hold the
     * slot), which mirrors the previous slot-taken rule.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public static function busyRanges(?int $dentistId, string $date, ?int $ignoreAppointmentId = null): array
    {
        $appointments = self::query()
            ->whereDate('appointment_date', $date)
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->when($dentistId !== null, fn (Builder $q) => $q->where(fn (Builder $q2) => $q2->where('dentist_id', $dentistId)->orWhereNull('dentist_id')))
            ->when($ignoreAppointmentId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->get(['time_slot', 'service_type', 'duration_minutes']);

        return $appointments
            ->map(function (self $a) {
                $start = self::toMinutes($a->time_slot);
                $minutes = (int) ($a->duration_minutes ?: self::durationFor($a->service_type));

                return [$start, $start + $minutes];
            })
            ->all();
    }

    /**
     * Would booking `minutes` from `slot` collide with an existing appointment?
     */
    public static function hasConflict(?int $dentistId, string $date, string $slot, ?int $minutes = null, ?int $ignoreAppointmentId = null): bool
    {
        $start = self::toMinutes($slot);
        $end = $start + ($minutes ?? (int) config('clinic.default_duration', 30));

        foreach (self::busyRanges($dentistId, $date, $ignoreAppointmentId) as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Backwards-compatible exact-slot check used by the booking guards.
     * Prefer hasConflict() when the procedure duration is known.
     */
    public static function isSlotTaken(?int $dentistId, string $date, string $slot, ?int $ignoreAppointmentId = null): bool
    {
        return self::hasConflict($dentistId, $date, $slot, null, $ignoreAppointmentId);
    }

    /**
     * Slot starts still free for a dentist on a date, for an appointment of the
     * given length. Closed days, off-duty days and past slots all come back empty.
     *
     * @return array<int, string>
     */
    public static function freeSlots(?int $dentistId, string $date, ?int $minutes = null): array
    {
        if (! self::isOpenOn($date) || ! self::isDentistOnDuty($dentistId, $date)) {
            return [];
        }

        $minutes ??= (int) config('clinic.default_duration', 30);

        return self::availableStarts($date, $minutes, self::busyRanges($dentistId, $date));
    }

    /**
     * Slot starts that fit a `$minutes`-long appointment around the given busy
     * ranges (as produced by busyRanges()). Pure schedule math — no queries.
     *
     * @param  array<int, array{0: int, 1: int}>  $busyRanges
     * @return array<int, string>
     */
    public static function availableStarts(string $date, int $minutes, array $busyRanges): array
    {
        $free = array_values(array_filter(self::slots(), function (string $slot) use ($minutes, $busyRanges, $date) {
            if (! self::fitsOpeningHours($slot, $minutes, $date)) {
                return false;
            }

            $start = self::toMinutes($slot);
            $end = $start + $minutes;

            foreach ($busyRanges as [$busyStart, $busyEnd]) {
                if ($start < $busyEnd && $end > $busyStart) {
                    return false;
                }
            }

            return true;
        }));

        // Past slots today are no longer bookable in real time.
        if ($date === now()->toDateString()) {
            $free = array_values(array_filter($free, fn (string $slot) => $slot > now()->format('H:i')));
        }

        return $free;
    }

    /** "09:00" -> 540 */
    public static function toMinutes(string $clock): int
    {
        [$h, $m] = array_pad(explode(':', $clock), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }

    /** 540 -> "09:00" */
    public static function toClock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'appointment_date',
        'time_slot',
        'service_type',
        'duration_minutes',
        'status',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'duration_minutes' => 'integer',
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

    /** Minutes reserved for this appointment (stored, or derived from the procedure). */
    public function getReservedMinutesAttribute(): int
    {
        return (int) ($this->duration_minutes ?: self::durationFor($this->service_type));
    }

    /** Formatted slot, e.g. "09:00" -> "09:00 AM". */
    public function getFormattedSlotAttribute(): string
    {
        return Carbon::createFromFormat('H:i', $this->time_slot)->format('h:i A');
    }

    /** End time of the appointment, e.g. "10:30 AM". */
    public function getFormattedEndAttribute(): string
    {
        return Carbon::createFromFormat('H:i', $this->time_slot)
            ->addMinutes($this->reserved_minutes)
            ->format('h:i A');
    }
}
