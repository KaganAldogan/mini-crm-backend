<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('property_id')->constrained('properties', 'uid')->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('users', 'uid')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers', 'uid')->nullOnDelete();
            $table->foreignUuid('consultant_user_id')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('rent_amount');
            $table->string('currency', 3)->default('TRY');
            $table->unsignedTinyInteger('due_day');
            $table->string('increase_period', 20)->default('yearly');
            $table->decimal('increase_rate_percent', 5, 2)->nullable();
            $table->date('next_increase_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_user_id', 'status']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
