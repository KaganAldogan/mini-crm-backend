<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_documents', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('lease_id')->constrained('leases', 'uid')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users', 'uid')->nullOnDelete();
            $table->string('type', 20)->default('other');
            $table->string('title')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index(['lease_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_documents');
    }
};
