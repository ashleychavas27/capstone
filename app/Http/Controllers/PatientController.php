<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PatientController extends Controller
{
    /**
     * Searchable patient records table (Page 2).
     */
    public function index(): View
    {
        return view('patients.index');
    }

    /**
     * JSON source for the DataTable (client-side search / sort / paginate).
     */
    public function data(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $patients = User::with(['patientProfile'])
            ->where('role', User::ROLE_PATIENT)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_no', 'like', "%{$search}%");
                });
            })
            ->withCount(['patientAppointments as total_appointments'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                // e() escapes stored user input before client-side rendering.
                'name' => e($u->name),
                'email' => e($u->email),
                'contact_no' => e((string) $u->contact_no),
                'age' => $u->patientProfile?->age,
                'address' => e((string) $u->patientProfile?->address),
                'medical_history' => e((string) $u->patientProfile?->medical_history),
                'total_appointments' => $u->total_appointments,
                'actions' => route('patients.show', $u),
            ]);

        return response()->json(['data' => $patients->values()]);
    }

    /**
     * Patient detail: profile, medical history and consultation history.
     */
    public function show(User $user): View
    {
        abort_unless($user->isPatient(), 404, 'Patient not found.');

        $user->load(['patientProfile', 'patientAppointments' => fn ($q) => $q->with(['dentist', 'treatmentRecord', 'prescriptions'])->orderByDesc('appointment_date')->orderBy('time_slot')]);

        return view('patients.show', ['patient' => $user]);
    }

    /**
     * Printable diagnostic / treatment history summary.
     */
    public function printHistory(User $user): View
    {
        abort_unless($user->isPatient(), 404, 'Patient not found.');

        $appointments = $user->patientAppointments()
            ->with(['dentist', 'treatmentRecord', 'prescriptions.items'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('time_slot')
            ->get();

        return view('patients.history-print', ['patient' => $user, 'appointments' => $appointments]);
    }

    /**
     * Dentist adds/updates the treatment record for an appointment.
     * Completes the appointment after the record is saved.
     */
    public function storeTreatment(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isPatient(), 404, 'Patient not found.');

        // Only Dentists may record treatment details.
        abort_unless(Auth::user()->isDentist(), 403, 'Only the Dentist can add treatment records.');

        $data = $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id'],
            'treatment_details' => ['required', 'string', 'max:2000'],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment = Appointment::findOrFail($data['appointment_id']);

        // The treatment must belong to this patient.
        abort_unless((int) $appointment->patient_id === (int) $user->id, 422, 'This appointment does not belong to the selected patient.');

        $appointment->treatmentRecord()->updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $user->id,
                'dentist_id' => Auth::id(),
                'treatment_details' => $data['treatment_details'],
                'clinical_notes' => $data['clinical_notes'] ?? null,
            ]
        );

        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
        }

        return back()->with('success', 'Treatment record saved and appointment marked as Completed.');
    }
}
