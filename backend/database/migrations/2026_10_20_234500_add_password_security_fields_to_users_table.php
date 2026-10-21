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
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (!Schema::hasColumn('users', 'password_delivery_status')) {
                $table->string('password_delivery_status', 50)->nullable()->default('manual')->after('must_change_password');
            }
            if (!Schema::hasColumn('users', 'password_delivered_at')) {
                $table->timestamp('password_delivered_at')->nullable()->after('password_delivery_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'password_delivered_at')) {
                $columns[] = 'password_delivered_at';
            }
            if (Schema::hasColumn('users', 'password_delivery_status')) {
                $columns[] = 'password_delivery_status';
            }
            if (Schema::hasColumn('users', 'must_change_password')) {
                $columns[] = 'must_change_password';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
