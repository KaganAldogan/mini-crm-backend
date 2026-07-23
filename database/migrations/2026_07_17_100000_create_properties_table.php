<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('listing_type');
            $table->string('property_type');
            $table->unsignedInteger('price');
            $table->string('location')->nullable();
            $table->string('rooms')->nullable();
            $table->unsignedInteger('area_sqm')->nullable();
            $table->string('status')->default('active');
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
