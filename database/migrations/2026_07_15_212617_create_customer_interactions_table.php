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
        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('customer_id')->constrained('customers', 'uid')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->string('type');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamp('interacted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
    }
};
