<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extend the status enum to include 'reopened'
        DB::statement("ALTER TABLE semester_submissions MODIFY COLUMN status ENUM('pending', 'submitted', 'under_review', 'approved', 'rejected', 'correction_required', 'reopened') DEFAULT 'pending'");

        // 2. Add reopening & locking tracking fields
        Schema::table('semester_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('semester_submissions', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('semester_submissions', 'reopened_by')) {
                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete()->after('reopened_at');
            }
            if (!Schema::hasColumn('semester_submissions', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable()->after('reopened_by');
            }
            if (!Schema::hasColumn('semester_submissions', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('reopen_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semester_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopened_at', 'reopen_reason', 'locked_at']);
        });

        DB::statement("ALTER TABLE semester_submissions MODIFY COLUMN status ENUM('pending', 'submitted', 'under_review', 'approved', 'rejected', 'correction_required') DEFAULT 'pending'");
    }
};
