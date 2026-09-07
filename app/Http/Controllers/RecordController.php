<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecordController extends Controller
{
    /**
     * Patient-facing records page: appointment history, diagnostic records
     * and e-prescriptions (patients only see their own data).
     */
    public function index(): View
    {
        $patient = Auth::user();

        $appointments = $patient->patientAppointments()
            ->with(['dentist', 'treatmentRecord', 'prescriptions.items'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('time_slot')
            ->get();

        return view('records.index', ['patient' => $patient, 'appointments' => $appointments]);
    }

    /**
     * Patient prints one of their own e-prescriptions (reuses the reseta layout).
     */
    public function printPrescription(Prescription $prescription): View
    {
        abort_unless((int) $prescription->patient_id === (int) Auth::id(), 403, 'You can only view your own prescriptions.');

        $prescription->load(['patient.patientProfile', 'dentist', 'items', 'appointment']);

        return view('prescriptions.print', ['prescription' => $prescription]);
    }
}
