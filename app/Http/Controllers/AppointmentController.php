<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
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
                'duration' => Appointment::minutesLabel($a->reserved_minutes),
                'ends_at' => $a->formatted_end,
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
            'time_slot' => ['required', Rule::in(Appointment::slots())],
            'service_type' => ['required', 'string', 'max:100'],
        ]);

        if (! Appointment::isOpenOn($data['appointment_date'])) {
            return back()->withErrors(['appointment_date' => 'The clinic is closed on that date.'])->withInput();
        }

        if (! Appointment::isDentistOnDuty($data['dentist_id'] ?? null, $data['appointment_date'])) {
            return back()->withErrors(['dentist_id' => 'That dentist is not on duty on the selected date.'])->withInput();
        }

        // Procedures reserve their estimated duration, so the guard must check
        // the whole reserved window, not just the starting slot.
        $minutes = Appointment::durationFor($data['service_type']);

        if (Appointment::hasConflict($data['dentist_id'] ?? null, $data['appointment_date'], $data['time_slot'], $minutes)) {
            return back()->withErrors(['time_slot' => 'This date and time slot is already booked. Please choose another slot.'])->withInput();
        }

        try {
            Appointment::create([
                'patient_id' => $data['patient_id'],
                'dentist_id' => $data['dentist_id'] ?? null,
                'appointment_date' => $data['appointment_date'],
                'time_slot' => $data['time_slot'],
                'service_type' => $data['service_type'],
                'duration_minutes' => $minutes,
                'status' => Appointment::STATUS_PENDING,
            ]);
        } catch (QueryException $e) {
            // Lost the race against the unique slot constraint — report as a booking conflict.
            if ($this->isUniqueConstraintViolation($e)) {
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
            'service_type' => ['nullable', 'string', 'max:100'],
        ]);

        $dentistId = $data['dentist_id'] ?? null;
        $service = $data['service_type'] ?? null;

        // The procedure decides how much time to reserve, so availability is
        // always asked for as "slots that fit this procedure".
        $minutes = $service !== null ? Appointment::durationFor($service) : null;
        $slots = Appointment::freeSlots($dentistId, $data['date'], $minutes);

        return response()->json([
            'date' => $data['date'],
            'slots' => $slots,
            'duration_minutes' => $minutes,
            'duration_label' => $service !== null ? Appointment::durationLabel($service) : null,
            'reason' => $slots ? null : $this->unavailableReason($dentistId, $data['date']),
        ]);
    }

    /**
     * Why is nothing free? Mirrors the rules Appointment::freeSlots() applies,
     * so the booking page can say something more useful than "no slots".
     */
    protected function unavailableReason(?int $dentistId, string $date): string
    {
        if (! Appointment::isOpenOn($date)) {
            return 'The clinic is closed on '.Carbon::parse($date)->format('l').
                '. We are open '.Appointment::openDaysLabel().', '.Appointment::hoursLabel().'.';
        }

        if (! Appointment::isDentistOnDuty($dentistId, $date)) {
            $dentist = $dentistId !== null ? User::find($dentistId) : null;

            return sprintf(
                'Dr. %s is not on duty on %s (duty days: %s). Please pick one of those days or another dentist.',
                $dentist?->name ?? 'that dentist',
                Carbon::parse($date)->format('l'),
                $dentist ? Appointment::dutyDayLabel($dentist) : '—'
            );
        }

        return 'No free slots left for this dentist on this date — try another day.';
    }

    /**
     * Per-day availability for a month — powers the passport-style booking
     * calendar. Returns each day's status (available / full / past / closed /
     * unavailable) plus the earliest bookable date across the whole window.
     */
    public function monthAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dentist_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_DENTIST)],
            'month' => ['required', 'date_format:Y-m'],
            'service_type' => ['nullable', 'string', 'max:100'],
        ]);

        $dentistId = $data['dentist_id'] ?? null;
        $service = $data['service_type'] ?? null;
        $minutes = $service !== null
            ? Appointment::durationFor($service)
            : (int) config('clinic.default_duration', 30);

        $monthStart = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $today = now()->startOfDay();
        $windowEnd = now()->addMonths((int) config('clinic.window_months', 2))->endOfDay();

        // One grouped query per month: which windows are already reserved on which day?
        $busyByDay = $this->busyRangesByDay($dentistId, $monthStart->toDateString(), $monthEnd->toDateString());
        $totalSlots = count(Appointment::slots());

        $days = [];
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $date = $day->toDateString();

            if ($day->lt($today)) {
                $days[] = ['date' => $date, 'status' => 'past', 'free_slots' => 0, 'total_slots' => $totalSlots];
            } elseif ($day->gt($windowEnd)) {
                $days[] = ['date' => $date, 'status' => 'unavailable', 'free_slots' => 0, 'total_slots' => $totalSlots];
            } elseif (! Appointment::isOpenOn($date)) {
                $days[] = ['date' => $date, 'status' => 'closed', 'free_slots' => 0, 'total_slots' => $totalSlots];
            } elseif (! Appointment::isDentistOnDuty($dentistId, $date)) {
                // The dentist simply does not work that day — not the same as the
                // clinic being closed, so the calendar shows it separately.
                $days[] = ['date' => $date, 'status' => 'off_duty', 'free_slots' => 0, 'total_slots' => $totalSlots];
            } else {
                $free = Appointment::availableStarts($date, $minutes, $busyByDay[$date] ?? []);

                $days[] = [
                    'date' => $date,
                    'status' => $free ? 'available' : 'full',
                    'free_slots' => count($free),
                    'total_slots' => $totalSlots,
                ];
            }
        }

        return response()->json([
            'month' => $data['month'],
            'dentist_id' => $dentistId,
            'duration_minutes' => $minutes,
            'earliest_available' => $this->earliestAvailableDate($dentistId, $today, $windowEnd, $minutes),
            'days' => $days,
        ]);
    }

    /**
     * Map of date => reserved [start, end] minute ranges for a date range
     * (non-cancelled appointments, using each appointment's stored duration).
     */
    protected function busyRangesByDay(?int $dentistId, string $from, string $to): array
    {
        return Appointment::query()
            ->whereBetween('appointment_date', [$from, $to])
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->when($dentistId !== null, fn ($q) => $q->where(fn ($q2) => $q2->where('dentist_id', $dentistId)->orWhereNull('dentist_id')))
            ->get(['appointment_date', 'time_slot', 'service_type', 'duration_minutes'])
            ->groupBy(fn ($a) => $a->appointment_date->toDateString())
            ->map(fn ($group) => $group->map(function (Appointment $a) {
                $start = Appointment::toMinutes($a->time_slot);

                return [$start, $start + $a->reserved_minutes];
            })->all())
            ->all();
    }

    /**
     * First bookable date within [today, windowEnd] for the given dentist and
     * appointment length (skips closed days and the dentist's off-duty days).
     */
    protected function earliestAvailableDate(?int $dentistId, Carbon $from, Carbon $to, int $minutes): ?string
    {
        $busyByDay = $this->busyRangesByDay($dentistId, $from->toDateString(), $to->toDateString());

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();

            if (! Appointment::isOpenOn($date) || ! Appointment::isDentistOnDuty($dentistId, $date)) {
                continue;
            }

            if (Appointment::availableStarts($date, $minutes, $busyByDay[$date] ?? [])) {
                return $date;
            }
        }

        return null;
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
            'time_slot' => ['required', Rule::in(Appointment::slots())],
            // Patients may only self-book procedures the clinic offers online;
            // "by appointment" procedures are arranged at the clinic, so staff
            // (who are doing that arranging) may still record them here.
            'service_type' => $isStaff
                ? ['required', 'string', 'max:100']
                : ['required', Rule::in(array_column(Appointment::onlineProcedures(), 'name'))],
            'patient_id' => $isStaff ? ['required', Rule::exists('users', 'id')->where('role', User::ROLE_PATIENT)] : ['prohibited'],
        ]);

        $patientId = $isStaff ? $data['patient_id'] : Auth::id();
        $minutes = Appointment::durationFor($data['service_type']);

        if (! Appointment::isOpenOn($data['appointment_date'])) {
            return response()->json([
                'message' => 'The clinic is closed on that date. We are open '.Appointment::openDaysLabel().', '.Appointment::hoursLabel().'.',
                'errors' => ['appointment_date' => ['Clinic closed on that date.']],
            ], 422);
        }

        if (! Appointment::isDentistOnDuty($data['dentist_id'], $data['appointment_date'])) {
            return response()->json([
                'message' => $this->unavailableReason($data['dentist_id'], $data['appointment_date']),
                'errors' => ['appointment_date' => ['Dentist not on duty.']],
            ], 422);
        }

        // Double-booking guard: refuse if the slot (or the time this procedure
        // reserves) was taken while the patient was choosing.
        if (Appointment::hasConflict($data['dentist_id'], $data['appointment_date'], $data['time_slot'], $minutes)) {
            return response()->json([
                'message' => 'Sorry, that time slot was just booked by someone else. Please pick another slot.',
                'errors' => ['time_slot' => ['Slot no longer available.']],
            ], 409);
        }

        try {
            $appointment = DB::transaction(function () use ($data, $patientId, $minutes) {
                return Appointment::create([
                    'patient_id' => $patientId,
                    'dentist_id' => $data['dentist_id'],
                    'appointment_date' => $data['appointment_date'],
                    'time_slot' => $data['time_slot'],
                    'service_type' => $data['service_type'],
                    'duration_minutes' => $minutes,
                    'status' => Appointment::STATUS_PENDING,
                ]);
            });
        } catch (QueryException $e) {
            // Lost the race against the unique slot constraint — report as a booking conflict.
            if ($this->isUniqueConstraintViolation($e)) {
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

    /**
     * Was this database error a unique-constraint violation?
     *
     * Used to detect losing the race for a time slot against the
     * `appt_unique_slot` constraint. The SQLSTATE is driver-specific:
     * MySQL and SQLite report 23000 (integrity constraint violation), while
     * PostgreSQL — and therefore Supabase — reports 23505 (unique violation).
     */
    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}
