<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'age',
        'address',
        'medical_history',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id', 'user_id');
    }

    public function treatmentRecords(): HasMany
    {
        return $this->hasMany(TreatmentRecord::class, 'patient_id', 'user_id');
    }
}
