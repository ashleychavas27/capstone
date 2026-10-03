<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: no ->after() here. Column placement is a MySQL-only clause that
        // PostgreSQL ignores, so the columns simply append to the table.
        Schema::table('users', function (Blueprint $table) {
            $table->string('license_no', 50)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['license_no', 'is_active']);
        });
    }
};
