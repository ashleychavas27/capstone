<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('dashboard'));

// ---------------------------------------------------------------------------
// Authentication (guests only)
// ---------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ---------------------------------------------------------------------------
// Authenticated area
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    // Page 1: Dashboard & reports
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/print', [DashboardController::class, 'printReport'])->name('dashboard.print')
        ->middleware('role:Owner,Secretary');

    // Page 3 (patient side): online booking with real-time availability
    Route::get('/appointments/book', [AppointmentController::class, 'bookForm'])->name('appointments.book');
    Route::get('/appointments/available-slots', [AppointmentController::class, 'availableSlots'])->name('appointments.available-slots');
    Route::post('/appointments/book', [AppointmentController::class, 'book'])->name('appointments.book.store');

    // Chat bot assistant
    Route::post('/chatbot', [ChatController::class, 'send'])->name('chatbot.send');

    // My Profile (self-service — every signed-in user)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Staff account management (Owner only)
    Route::middleware('role:Owner')->group(function () {
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/data', [AccountController::class, 'data'])->name('accounts.data');
        Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{user}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{user}', [AccountController::class, 'update'])->name('accounts.update');
        Route::patch('/accounts/{user}/toggle-active', [AccountController::class, 'toggleActive'])->name('accounts.toggle-active');
    });

    // Page 2: Patient records & treatment history (staff)
    Route::middleware('role:Owner,Secretary,Dentist')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/data', [PatientController::class, 'data'])->name('patients.data');
        Route::get('/patients/{user}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('/patients/{user}/history/print', [PatientController::class, 'printHistory'])->name('patients.history.print');
        Route::post('/patients/{user}/treatment', [PatientController::class, 'storeTreatment'])->name('patients.treatment.store');

        // Calendar view of the schedule (Dentists see only their own)
        Route::get('/appointments/calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
        Route::get('/appointments/calendar-events', [AppointmentController::class, 'calendarEvents'])->name('appointments.calendar-events');
    });

    // e-Prescription (reseta): everyone on staff can view; Dentist issues & edits
    Route::middleware('role:Owner,Secretary,Dentist')->group(function () {
        Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
        Route::get('/prescriptions/data', [PrescriptionController::class, 'data'])->name('prescriptions.data');
        Route::get('/prescriptions/create', [PrescriptionController::class, 'create'])
            ->middleware('role:Dentist')->name('prescriptions.create');
        Route::get('/prescriptions/{prescription}/edit', [PrescriptionController::class, 'edit'])
            ->middleware('role:Dentist')->name('prescriptions.edit');
        Route::put('/prescriptions/{prescription}', [PrescriptionController::class, 'update'])
            ->middleware('role:Dentist')->name('prescriptions.update');
        Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])
            ->middleware('role:Owner,Dentist')->name('prescriptions.destroy');
        Route::post('/prescriptions', [PrescriptionController::class, 'store'])
            ->middleware('role:Dentist')->name('prescriptions.store');
        Route::get('/prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->name('prescriptions.print');
    });

    // Page 3 (staff side): schedule management + SMS trigger
    Route::middleware('role:Owner,Secretary')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/data', [AppointmentController::class, 'data'])->name('appointments.data');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

        // SMS notification history
        Route::get('/sms', [SmsController::class, 'index'])->name('sms.index');
        Route::get('/sms/data', [SmsController::class, 'data'])->name('sms.data');
        Route::post('/sms', [SmsController::class, 'send'])->name('sms.send');
    });
});
