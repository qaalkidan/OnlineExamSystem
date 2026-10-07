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
        // 1. Add cancellation and pause fields to exams table
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->timestamp('paused_at')->nullable()->after('cancellation_reason');
            $table->foreignId('paused_by')->nullable()->after('paused_at')->constrained('users')->nullOnDelete();
        });

        // 2. Add extra time and heartbeat tracking to exam_attempts table
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->integer('extra_time_seconds')->default(0)->after('status');
            $table->timestamp('last_heartbeat_at')->nullable()->after('extra_time_seconds');
            $table->boolean('is_cancelled')->default(false)->after('last_heartbeat_at');
        });

        // 3. Create exam_recovery_requests table
        Schema::create('exam_recovery_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Student
            $table->timestamp('disconnected_at');
            $table->timestamp('reconnected_at')->nullable();
            $table->integer('interruption_seconds')->default(0);
            $table->integer('suggested_seconds')->default(0);
            $table->integer('approved_seconds')->nullable();
            $table->string('status', 50)->default('pending_approval');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'status']);
            $table->index(['user_id', 'exam_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_recovery_requests');

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['extra_time_seconds', 'last_heartbeat_at', 'is_cancelled']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['paused_by']);
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_reason', 'paused_at', 'paused_by']);
        });
    }
};
