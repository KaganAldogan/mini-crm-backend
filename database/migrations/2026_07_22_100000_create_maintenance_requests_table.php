<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('tenant_user_id')->constrained('users', 'uid')->cascadeOnDelete();
            $table->foreignUuid('lease_id')->nullable()->constrained('leases', 'uid')->nullOnDelete();
            $table->foreignUuid('property_id')->nullable()->constrained('properties', 'uid')->nullOnDelete();
            $table->foreignUuid('technician_user_id')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->string('category', 40);
            $table->string('title');
            $table->text('description');
            $table->string('status', 30)->default('pending');
            $table->text('technician_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
