<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE semester_submissions MODIFY COLUMN status ENUM('pending', 'submitted', 'under_review', 'approved', 'rejected', 'correction_required') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE semester_submissions MODIFY COLUMN status ENUM('pending', 'submitted', 'approved', 'rejected') DEFAULT 'pending'");
    }
};
