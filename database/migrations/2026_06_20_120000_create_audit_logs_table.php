<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('summary');
            $table->foreignUuid('workbook_id')->nullable()->constrained()->nullOnDelete();
            $table->string('workbook_name')->nullable();
            $table->foreignUuid('sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sheet_name')->nullable();
            $table->string('target')->nullable();
            $table->string('operation_id', 64)->nullable()->index();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workbook_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
