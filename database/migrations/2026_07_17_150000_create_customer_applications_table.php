<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_applications', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 11);
            $table->string('interest_type')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers', 'uid')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_applications');
    }
};
