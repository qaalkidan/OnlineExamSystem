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
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return;
        }
        // Add 'published' to the ENUM
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE exam_attempts MODIFY COLUMN status ENUM('in_progress', 'submitted', 'graded', 'published') DEFAULT 'in_progress'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return;
        }
        // Revert back
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE exam_attempts MODIFY COLUMN status ENUM('in_progress', 'submitted', 'graded') DEFAULT 'in_progress'");
    }
};
