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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Modify the ENUM column to include 'dept_head'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'instructor', 'student', 'dept_head') NOT NULL DEFAULT 'student'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Revert back (note: this will fail if there are any 'dept_head' records, but it's okay for down migration)
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'instructor', 'student') NOT NULL DEFAULT 'student'");
    }
};
