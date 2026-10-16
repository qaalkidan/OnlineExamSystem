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
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('log_status');
            $table->index('module');
            $table->index('type');
            $table->index('is_read');
            $table->index('actor_role');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['log_status']);
            $table->dropIndex(['module']);
            $table->dropIndex(['type']);
            $table->dropIndex(['is_read']);
            $table->dropIndex(['actor_role']);
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
