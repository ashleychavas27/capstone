<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    /**
     * Calendar view of the schedule (Owner / Secretary see all; Dentist sees own).
     */
    public function calendar(): View
    {
        $dentists = User::where('role', User::ROLE_DENTIST)->orderBy('name')->get();

        return view('appointments.calendar', [
            'dentists' => $dentists,
            'canManage' => Auth::user()->isStaff() && ! Auth::user()->isDentist(),
            'filterToSelf' => Auth::user()->isDentist(),
        ]);
    }

    /**
     * JSON event feed for FullCalendar.
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'dentist_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_DENTIST)],
        ]);

        $query = Appointment::with(['patient', 'dentist']);

        if ($request->filled('start')) {
            $query->whereDate('appointment_date', '>=', $data['start']);
        }

        if ($request->filled('end')) {
            $query->whereDate('appointment_date', '<=', $data['end']);
        }

        // Dentists only ever see their own schedule.
        if (Auth::user()->isDentist()) {
            $query->where('dentist_id', Auth::id());
        } elseif (! empty($data['dentist_id'])) {
            $query->where('dentist_id', $data['dentist_id']);
        }

        $events = $query->get()->map(fn (Appointment $a) => [
            'id' => $a->id,
            'title' => sprintf('%s · %s', $a->formatted_slot, e($a->patient?->name ?? 'Patient')),
            'start' => $a->appointment_date->toDateString().' '.$a->time_slot,
            'backgroundColor' => match ($a->status) {
                Appointment::STATUS_PENDING => '#D1987F',
                Appointment::STATUS_CONFIRMED => '#C89B27',
                Appointment::STATUS_COMPLETED => '#B4B1B2',
                default => '#9A8F8E',
            },
            'borderColor' => '#FFFFFF',
            'textColor' => in_array($a->status, [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED]) ? '#3D3428' : '#FFFFFF',
            'extendedProps' => [
                'patient' => e($a->patient?->name ?? '—'),
                'dentist' => e($a->dentist?->name ?? 'Unassigned'),
                'service_type' => e($a->service_type),
                'time_slot' => $a->formatted_slot,
                'status' => $a->status,
            ],
        ]);

        return response()->json($events);
    }

    /**
     * Staff schedule management (Page 3, staff side).
     */
    public function index(): View
    {
        $patients = User::where('role', User::ROLE_PATIENT)->orderBy('name')->get();
        $dentists = User::where('role', User::ROLE_DENTIST)->orderBy('name')->get();

        return view('appointments.index', compact('patients', 'dentists'));
    }

    /**
     * JSON source for the appointments DataTable.
     */
    public function data(Request $request): JsonResponse
    {
        $query = Appointment::with(['patient', 'dentist', 'treatmentRecord']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->query('date'));
        }

        $appointments = $query->orderByDesc('appointment_date')->orderBy('time_slot')->get();

        $rows = $appointments->map(function (Appointment $a) {
            return [
                'id' => $a->id,
                // e() escapes stored user input before client-side rendering.
                'patient' => e($a->patient?->name ?? '—'),
                'patient_contact' => e($a->patient?->contact_no ?? '—'),
                'dentist' => e($a->dentist?->name ?? 'Unassigned'),
                'date' => $a->appointment_date->format('M d, Y'),
                'date_raw' => $a->appointment_date->toDateString(),
                'time_slot' => $a->formatted_slot,
                'service_type' => e($a->service_type),
                'status' => $a->status,
                'has_treatment' => $a->treatmentRecord !== null,
                'editable' => Auth::user()->isStaff(),
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * Staff manually books an appointment for a patient (with conflict check).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_PATIENT)],
            'dentist_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_DENTIST)],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', Rule::in(Appointment::SLOTS)],
            'service_type' => ['required', 'string', 'max:100'],
        ]);

        if (Appointment::isSlotTaken($data['dentist_id'] ?? null, $data['appointment_date'], $data['time_slot'])) {
            return back()->withErrors(['time_slot' => 'This date and time slot is already booked. Please choose another slot.'])->withInput();
        }

        try {
            Appointment::create([
                'patient_id' => $data['patient_id'],
                'dentist_id' => $data['dentist_id'] ?? null,
                'appointment_date' => $data['appointment_date'],
                'time_slot' => $data['time_slot'],
                'service_type' => $data['service_type'],
                'status' => Appointment::STATUS_PENDING,
            ]);
        } catch (QueryException $e) {
            // Lost the race against the unique slot constraint — report as a booking conflict.
            if ($e->getCode() === '23000') {
                return back()->withErrors(['time_slot' => 'This date and time slot was just booked by someone else. Please choose another slot.'])->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Appointment scheduled successfully.');
    }

    /**
     * Patient-facing online booking form (Page 3).
     */
    public function bookForm(): View
    {
        $dentists = User::where('role', User::ROLE_DENTIST)->orderBy('name')->get();
        $patients = User::where('role', User::ROLE_PATIENT)->orderBy('name')->get();

        return view('appointments.book', compact('dentists', 'patients'));
    }

    /**
     * Real-time availability endpoint: returns the slots still free for a
     * dentist on a given date (used via AJAX when the form changes).
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'dentist_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_DENTIST)],
        ]);

        return response()->json([
            'date' => $data['date'],
            'slots' => Appointment::freeSlots($data['dentist_id'] ?? null, $data['date']),
        ]);
    }

    /**
     * Patient submits an online booking.
     */
    public function book(Request $request): JsonResponse
    {
        $isStaff = Auth::user()->isStaff();

        $data = $request->validate([
            'dentist_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_DENTIST)],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', Rule::in(Appointment::SLOTS)],
            'service_type' => ['required', 'string', 'max:100'],
            'patient_id' => $isStaff ? ['required', Rule::exists('users', 'id')->where('role', User::ROLE_PATIENT)] : ['prohibited'],
        ]);

        $patientId = $isStaff ? $data['patient_id'] : Auth::id();

        // Double-booking guard: refuse if the slot was just taken.
        if (Appointment::isSlotTaken($data['dentist_id'], $data['appointment_date'], $data['time_slot'])) {
            return response()->json([
                'message' => 'Sorry, that time slot was just booked by someone else. Please pick another slot.',
                'errors' => ['time_slot' => ['Slot no longer available.']],
            ], 409);
        }

        try {
            $appointment = DB::transaction(function () use ($data, $patientId) {
                return Appointment::create([
                    'patient_id' => $patientId,
                    'dentist_id' => $data['dentist_id'],
                    'appointment_date' => $data['appointment_date'],
                    'time_slot' => $data['time_slot'],
                    'service_type' => $data['service_type'],
                    'status' => Appointment::STATUS_PENDING,
                ]);
            });
        } catch (QueryException $e) {
            // Lost the race against the unique slot constraint — report as a booking conflict.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Sorry, that time slot was just booked by someone else. Please pick another slot.',
                    'errors' => ['time_slot' => ['Slot no longer available.']],
                ], 409);
            }

            throw $e;
        }

        // Notify the patient that the booking request was received (Pending SMS).
        $sms = app(SmsService::class)->sendBookingAcknowledgment($appointment);

        return response()->json([
            'message' => 'Appointment booked successfully! You will receive an SMS once it is confirmed by the clinic.',
            'appointment' => [
                'id' => $appointment->id,
                'date' => $appointment->appointment_date->format('M d, Y'),
                'time_slot' => $appointment->formatted_slot,
                'service_type' => $appointment->service_type,
                'status' => $appointment->status,
            ],
        ], 201);
    }

    /**
     * Update appointment status.
     *
     * When status becomes "Confirmed", an SMS reminder is automatically
     * triggered via the SMS service and recorded in sms_logs.
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Appointment::STATUSES)],
        ]);

        $wasConfirmed = $appointment->status === Appointment::STATUS_CONFIRMED;
        $wasCancelled = $appointment->status === Appointment::STATUS_CANCELLED;
        $appointment->update(['status' => $data['status']]);

        $sms = null;
        if ($data['status'] === Appointment::STATUS_CONFIRMED && ! $wasConfirmed) {
            $sms = app(SmsService::class)->sendAppointmentConfirmation($appointment);
        }

        if ($data['status'] === Appointment::STATUS_CANCELLED && ! $wasCancelled) {
            $sms = app(SmsService::class)->sendCancellationNotice($appointment);
        }

        return response()->json([
            'message' => "Appointment marked as {$data['status']}.",
            'sms_triggered' => $sms !== null,
            'sms_status' => $sms?->status,
        ]);
    }

    /**
     * Staff cancels / removes an appointment. Sends a cancellation SMS first
     * so the patient is informed before the record is removed.
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        if ($appointment->status !== Appointment::STATUS_CANCELLED) {
            app(SmsService::class)->sendCancellationNotice($appointment);
        }

        $appointment->delete();

        return response()->json(['message' => 'Appointment removed.']);
    }
}
