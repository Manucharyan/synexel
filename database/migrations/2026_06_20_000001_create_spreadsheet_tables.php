<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workbooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workbook_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('index')->default(0);
            $table->json('layout')->nullable();
            $table->timestamps();

            $table->unique(['workbook_id', 'name']);
        });

        Schema::create('cells', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row');
            $table->unsignedInteger('col');
            $table->text('raw_value')->nullable();
            $table->text('formula')->nullable();
            $table->text('computed_value')->nullable();
            $table->json('style')->nullable();
            $table->string('value_type', 32)->default('string');
            $table->timestamps();

            $table->unique(['sheet_id', 'row', 'col']);
            $table->index(['sheet_id', 'row', 'col']);
        });

        Schema::create('named_ranges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workbook_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sheet_name');
            $table->string('range_a1');
            $table->timestamps();

            $table->unique(['workbook_id', 'name']);
        });

        Schema::create('charts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workbook_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('definition');
            $table->timestamps();
        });

        Schema::create('conditional_formats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workbook_id')->constrained()->cascadeOnDelete();
            $table->string('sheet_name');
            $table->string('range_a1');
            $table->string('rule_type');
            $table->string('formula')->nullable();
            $table->json('style');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('cell_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('operation_id')->index();
            $table->foreignUuid('workbook_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row');
            $table->unsignedInteger('col');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->boolean('reverted')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('cell_changes');
        Schema::dropIfExists('conditional_formats');
        Schema::dropIfExists('charts');
        Schema::dropIfExists('named_ranges');
        Schema::dropIfExists('cells');
        Schema::dropIfExists('sheets');
        Schema::dropIfExists('workbooks');
    }
};
