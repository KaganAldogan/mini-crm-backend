<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_interest_events', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('property_id')->constrained('properties', 'uid')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('occurred_at');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_interest_events');
    }
};
