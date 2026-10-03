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
        Schema::table('users', function (Blueprint $table) {
            // Duty days for a dentist, as a comma-separated list of ISO weekdays
            // (e.g. "1,2,3,5" = Mon, Tue, Wed, Fri). Nullable: an account with no
            // days recorded is available every open day.
            $table->string('duty_days', 20)->nullable()->after('license_no');
        });

        // Seed the clinic's real duty schedule on the existing dentist accounts.
        foreach (['dentist1@clinic.test' => '1,2,3,5', 'dentist2@clinic.test' => '4,6'] as $email => $days) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('email', $email)
                ->whereNull('duty_days')
                ->update(['duty_days' => $days]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('duty_days');
        });
    }
};
