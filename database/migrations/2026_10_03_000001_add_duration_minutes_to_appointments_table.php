<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Minutes reserved for this appointment, taken from the procedure's
            // estimated duration at booking time. Stored (rather than re-derived
            // from service_type) so editing config/clinic.php can never silently
            // move an appointment that already exists.
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('service_type');
        });

        // Backfill existing rows from the configured procedure durations.
        $procedures = collect(config('clinic.procedures', []))
            ->merge(config('clinic.services', []))
            ->pluck('minutes', 'name');

        foreach ($procedures as $name => $minutes) {
            if ($name === null) {
                continue;
            }

            DB::table('appointments')
                ->where('service_type', $name)
                ->whereNull('duration_minutes')
                ->update(['duration_minutes' => $minutes ?? config('clinic.default_duration', 30)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
