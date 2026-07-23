<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_images', function (Blueprint $table) {
            $table->uuid('uid')->primary();
            $table->foreignUuid('property_id')->constrained('properties', 'uid')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
        });

        $properties = DB::table('properties')
            ->whereNotNull('cover_image')
            ->where('cover_image', '!=', '')
            ->get(['uid', 'cover_image', 'created_at', 'updated_at']);

        foreach ($properties as $property) {
            $exists = DB::table('property_images')
                ->where('property_id', $property->uid)
                ->where('path', $property->cover_image)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('property_images')->insert([
                'uid' => (string) Str::uuid(),
                'property_id' => $property->uid,
                'path' => $property->cover_image,
                'original_name' => basename($property->cover_image),
                'mime' => null,
                'size' => 0,
                'sort_order' => 0,
                'created_at' => $property->created_at ?? now(),
                'updated_at' => $property->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_images');
    }
};
