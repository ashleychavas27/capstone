<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    /**
     * List of prescriptions (staff side, DataTable).
     */
    public function index(): View
    {
        return view('prescriptions.index');
    }

    /**
     * JSON source for the prescriptions DataTable.
     */
    public function data(): JsonResponse
    {
        $rows = Prescription::with(['patient', 'dentist', 'items'])
            ->orderByDesc('date_issued')
            ->get()
            ->map(fn (Prescription $p) => [
                'id' => $p->id,
                // e() escapes stored user input before client-side rendering.
                'patient' => e($p->patient?->name ?? '—'),
                'dentist' => e($p->dentist?->name ?? '—'),
                'date_issued' => $p->date_issued->format('M d, Y'),
                'diagnosis' => e(Str::limit($p->diagnosis, 60)),
                'medications' => $p->items->count(),
                'can_edit' => Auth::user()->isDentist(),
            ]);

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * Dentist creates a new prescription (reseta).
     */
    public function create(Request $request): View
    {
        $patients = User::where('role', User::ROLE_PATIENT)->orderBy('name')->get();
        $appointments = Appointment::with(['patient', 'dentist'])
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_CONFIRMED])
            ->orderByDesc('appointment_date')
            ->limit(100)
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = User::find((int) $request->query('patient_id'));
        }

        return view('prescriptions.form', [
            'prescription' => null,
            'patients' => $patients,
            'dentists' => User::where('role', User::ROLE_DENTIST)->orderBy('name')->get(),
            'appointments' => $appointments,
            'selectedPatient' => $selectedPatient,
        ]);
    }

    /**
     * Store a new prescription with its medication items.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isDentist(), 403, 'Only the Dentist can issue prescriptions.');

        [$validated, $items] = $this->validatePrescription($request);

        DB::transaction(function () use ($validated, $items) {
            $prescription = Prescription::create($validated);

            foreach ($items as $item) {
                $prescription->items()->create($item);
            }
        });

        return redirect()->route('prescriptions.index')->with('success', 'Prescription issued successfully.');
    }

    /**
     * Dentist edits an existing prescription.
     */
    public function edit(Prescription $prescription): View
    {
        $prescription->load(['items', 'patient', 'dentist']);

        return view('prescriptions.form', [
            'prescription' => $prescription,
            'patients' => User::where('role', User::ROLE_PATIENT)->orderBy('name')->get(),
            'dentists' => User::where('role', User::ROLE_DENTIST)->orderBy('name')->get(),
            'appointments' => Appointment::with(['patient', 'dentist'])
                ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_CONFIRMED])
                ->orderByDesc('appointment_date')
                ->limit(100)
                ->get(),
            'selectedPatient' => null,
        ]);
    }

    /**
     * Update a prescription and replace its items.
     */
    public function update(Request $request, Prescription $prescription): RedirectResponse
    {
        abort_unless(Auth::user()->isDentist(), 403, 'Only the Dentist can update prescriptions.');

        [$validated, $items] = $this->validatePrescription($request);

        DB::transaction(function () use ($prescription, $validated, $items) {
            $prescription->update($validated);
            $prescription->items()->delete();

            foreach ($items as $item) {
                $prescription->items()->create($item);
            }
        });

        return redirect()->route('prescriptions.index')->with('success', 'Prescription updated successfully.');
    }

    /**
     * Delete a prescription.
     */
    public function destroy(Prescription $prescription): JsonResponse
    {
        $prescription->delete();

        return response()->json(['message' => 'Prescription deleted.']);
    }

    /**
     * Printable reseta view.
     */
    public function print(Prescription $prescription): View
    {
        $prescription->load(['patient.patientProfile', 'dentist', 'items', 'appointment']);

        return view('prescriptions.print', ['prescription' => $prescription]);
    }

    /**
     * Shared validation: prescription header + dynamic list of medication rows.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, ?string>>}
     */
    protected function validatePrescription(Request $request): array
    {
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_PATIENT)],
            'dentist_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_DENTIST)],
            'appointment_id' => ['nullable', Rule::exists('appointments', 'id')],
            'diagnosis' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'date_issued' => ['required', 'date'],
            'drug_name' => ['required', 'array', 'min:1'],
            'drug_name.*' => ['required', 'string', 'max:200'],
            'dosage' => ['nullable', 'array'],
            'dosage.*' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'array'],
            'frequency.*' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'array'],
            'duration.*' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'array'],
            'quantity.*' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'array'],
            'instructions.*' => ['nullable', 'string', 'max:500'],
        ]);

        $items = [];
        foreach ($data['drug_name'] as $i => $drugName) {
            $items[] = [
                'drug_name' => trim($drugName),
                'dosage' => trim($data['dosage'][$i] ?? '') ?: null,
                'frequency' => trim($data['frequency'][$i] ?? '') ?: null,
                'duration' => trim($data['duration'][$i] ?? '') ?: null,
                'quantity' => trim($data['quantity'][$i] ?? '') ?: null,
                'instructions' => trim($data['instructions'][$i] ?? '') ?: null,
            ];
        }

        unset($data['drug_name'], $data['dosage'], $data['frequency'], $data['duration'], $data['quantity'], $data['instructions']);

        return [$data, $items];
    }
}
