<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** Role constants used across the application. */
    public const ROLE_OWNER = 'Owner';

    public const ROLE_SECRETARY = 'Secretary';

    public const ROLE_DENTIST = 'Dentist';

    public const ROLE_PATIENT = 'Patient';

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_SECRETARY,
        self::ROLE_DENTIST,
        self::ROLE_PATIENT,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'contact_no',
        'role',
        'license_no',
        'is_active',
        'duty_days',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isSecretary(): bool
    {
        return $this->role === self::ROLE_SECRETARY;
    }

    public function isDentist(): bool
    {
        return $this->role === self::ROLE_DENTIST;
    }

    public function isPatient(): bool
    {
        return $this->role === self::ROLE_PATIENT;
    }

    /** Staff accounts that manage the clinic (Owner / Secretary / Dentist). */
    public function isStaff(): bool
    {
        return ! $this->isPatient();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Weekdays this dentist is on duty, as ISO numbers (1 = Monday … 7 = Sunday).
     *
     * Stored on the account as a comma-separated list ("1,2,3,5") so the clinic
     * can change a dentist's days without touching code. An account with no days
     * recorded is treated as available every open day.
     *
     * @return array<int, int>
     */
    public function dutyDays(): array
    {
        $raw = trim((string) ($this->duty_days ?? ''));

        if ($raw === '') {
            return config('clinic.default_duty_days', [1, 2, 3, 4, 5, 6]);
        }

        $days = array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[,\s]+/', $raw) ?: []),
            fn (int $day) => $day >= 1 && $day <= 7
        )));

        sort($days);

        return $days ?: config('clinic.default_duty_days', [1, 2, 3, 4, 5, 6]);
    }

    /** Duty days as a label, e.g. "Mon, Tue, Wed, Fri". */
    public function dutyDayLabel(): string
    {
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return implode(', ', array_map(fn (int $day) => $names[$day] ?? '', $this->dutyDays()));
    }

    /** Is this user working on the given date? (Clinic must also be open.) */
    public function isOnDutyOn(string|\DateTimeInterface $date): bool
    {
        return in_array((int) \Carbon\Carbon::parse($date)->isoWeekday(), $this->dutyDays(), true);
    }

    /**
     * The patient's clinical profile (only relevant for role = Patient).
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class, 'user_id');
    }

    /**
     * Appointments this user booked as a patient.
     */
    public function patientAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    /**
     * Appointments assigned to this user as the attending dentist.
     */
    public function dentistAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'dentist_id');
    }

    /**
     * Treatment records authored by this user as the attending dentist.
     */
    public function dentistTreatmentRecords(): HasMany
    {
        return $this->hasMany(TreatmentRecord::class, 'dentist_id');
    }

    /**
     * Treatment records for this user as a patient.
     */
    public function treatmentRecords(): HasMany
    {
        return $this->hasMany(TreatmentRecord::class, 'patient_id');
    }

    /**
     * Prescriptions issued to this user as a patient.
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'patient_id');
    }

    /**
     * Prescriptions authored by this user as the prescribing dentist.
     */
    public function dentistPrescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'dentist_id');
    }
}
