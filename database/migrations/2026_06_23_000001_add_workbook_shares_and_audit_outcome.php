<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workbook_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 16)->default('read');
            $table->timestamps();

            $table->unique(['workbook_id', 'user_id']);
            $table->index(['user_id', 'permission']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('outcome', 16)->default('success')->after('action');
            $table->string('resource_type', 64)->nullable()->after('outcome');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'resource_type']);
        });

        Schema::dropIfExists('workbook_shares');
    }
};
