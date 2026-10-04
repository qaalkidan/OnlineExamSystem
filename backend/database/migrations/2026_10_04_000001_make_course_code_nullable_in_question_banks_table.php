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
        try {
            Schema::table('question_banks', function (Blueprint $table) {
                $table->string('course_code')->nullable()->default('GENERAL')->change();
            });
        } catch (\Throwable $e) {
            DB::statement("ALTER TABLE `question_banks` MODIFY `course_code` VARCHAR(255) NULL DEFAULT 'GENERAL'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('question_banks', function (Blueprint $table) {
                $table->string('course_code')->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            DB::statement("ALTER TABLE `question_banks` MODIFY `course_code` VARCHAR(255) NOT NULL");
        }
    }
};
