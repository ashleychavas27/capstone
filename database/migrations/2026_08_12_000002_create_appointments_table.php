<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dentist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('appointment_date');
            $table->string('time_slot', 10); // e.g. "09:00" (24h) -> displayed as 09:00 AM
            $table->string('service_type', 100);
            $table->string('status', 20)->default('Pending'); // Pending, Confirmed, Completed, Cancelled
            $table->timestamps();

            // Database-level double-booking guard for the same dentist, date and slot.
            //
            // nullsNotDistinct() is essential on PostgreSQL: a plain unique index
            // treats every NULL as distinct, so it would NOT stop two unassigned
            // (dentist_id IS NULL) bookings from taking the same slot. It makes the
            // constraint enforce one live booking per slot including that case.
            // Requires PostgreSQL 15+ (Supabase is 15 or newer); other drivers
            // such as SQLite ignore the modifier.
            $table->unique(['dentist_id', 'appointment_date', 'time_slot'], 'appt_unique_slot')
                ->nullsNotDistinct();

            $table->index(['appointment_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
