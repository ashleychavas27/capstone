<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
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

    // Page 2: Patient records & treatment history (staff)
    Route::middleware('role:Owner,Secretary,Dentist')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/data', [PatientController::class, 'data'])->name('patients.data');
        Route::get('/patients/{user}', [PatientController::class, 'show'])->name('patients.show');
        Route::post('/patients/{user}/treatment', [PatientController::class, 'storeTreatment'])->name('patients.treatment.store');
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
