<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_images', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('maintenance_request_id')
                ->constrained('maintenance_requests', 'uid')
                ->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['maintenance_request_id', 'sort_order'],
                'maint_req_images_req_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_images');
    }
};
