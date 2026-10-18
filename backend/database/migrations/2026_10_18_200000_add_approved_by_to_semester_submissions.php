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
        Schema::table('semester_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('semester_submissions', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semester_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('semester_submissions', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
        });
    }
};
