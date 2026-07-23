<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_messages', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('lease_id')->constrained('leases', 'uid')->cascadeOnDelete();
            $table->foreignUuid('sender_user_id')->constrained('users', 'uid')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'created_at']);
            $table->index(['lease_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_messages');
    }
};
