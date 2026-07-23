<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_categories', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('maintenance_category_user', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->constrained('users', 'uid')
                ->cascadeOnDelete();
            $table->foreignUuid('maintenance_category_id')
                ->constrained('maintenance_categories', 'uid')
                ->cascadeOnDelete();
            $table->primary(['user_id', 'maintenance_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_category_user');
        Schema::dropIfExists('maintenance_categories');
    }
};
