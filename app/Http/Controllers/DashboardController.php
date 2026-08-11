<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = now()->toDateString();
        $user = Auth::user();

        // Patients only ever see their own data; staff see clinic-wide metrics.
        if ($user->isPatient()) {
            $metrics = [
                'totalPatients' => $user->patientAppointments()->count(),
                'upcomingAppointments' => $user->patientAppointments()
                    ->whereDate('appointment_date', '>=', $today)
                    ->where('status', '!=', Appointment::STATUS_CANCELLED)
                    ->count(),
                'pendingBookings' => $user->patientAppointments()->where('status', Appointment::STATUS_PENDING)->count(),
                'todayAppointments' => $user->patientAppointments()
                    ->whereDate('appointment_date', $today)
                    ->where('status', '!=', Appointment::STATUS_CANCELLED)
                    ->count(),
            ];
        } else {
            $metrics = [
                'totalPatients' => User::where('role', User::ROLE_PATIENT)->count(),
                'upcomingAppointments' => Appointment::whereDate('appointment_date', '>=', $today)
                    ->where('status', '!=', Appointment::STATUS_CANCELLED)
                    ->count(),
                'pendingBookings' => Appointment::where('status', Appointment::STATUS_PENDING)->count(),
                'todayAppointments' => Appointment::whereDate('appointment_date', $today)
                    ->where('status', '!=', Appointment::STATUS_CANCELLED)
                    ->count(),
            ];
        }

        // Daily schedule: today by default, any date via ?date=YYYY-MM-DD
        $date = $request->query('date', $today);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $date = $today;
        }

        $schedule = Appointment::with(['patient', 'dentist', 'treatmentRecord'])
            ->whereDate('appointment_date', $date)
            ->orderBy('time_slot')
            ->get();

        // Dentists see their own schedule; patients see their own bookings.
        if ($user->isDentist()) {
            $schedule = $schedule->where('dentist_id', $user->id)->values();
        } elseif ($user->isPatient()) {
            $schedule = $schedule->where('patient_id', $user->id)->values();
        }

        $dentists = User::where('role', User::ROLE_DENTIST)->orderBy('name')->get();

        // SMS feed contains other patients' phone numbers — staff only.
        $recentSms = $user->isStaff() ? SmsLog::latest()->take(5)->get() : collect();

        return view('dashboard.index', compact('metrics', 'schedule', 'date', 'dentists', 'recentSms'));
    }

    /**
     * Print-friendly report view (renders without the app navigation chrome).
     */
    public function printReport(Request $request): View
    {
        $today = now()->toDateString();
        $date = $request->query('date', $today);

        $schedule = Appointment::with(['patient', 'dentist'])
            ->whereDate('appointment_date', $date)
            ->orderBy('time_slot')
            ->get();

        $metrics = [
            'totalPatients' => User::where('role', User::ROLE_PATIENT)->count(),
            'upcomingAppointments' => Appointment::whereDate('appointment_date', '>=', $today)
                ->where('status', '!=', Appointment::STATUS_CANCELLED)
                ->count(),
        ];

        return view('dashboard.print', compact('schedule', 'date', 'metrics'));
    }
}
