<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_payments', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('lease_id')->constrained('leases', 'uid')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TRY');
            $table->date('paid_at')->nullable();
            $table->string('period_label', 100);
            $table->string('status', 20)->default('paid');
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->timestamps();

            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_payments');
    }
};
