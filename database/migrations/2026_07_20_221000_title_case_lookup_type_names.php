<?php

use App\Models\ListingType;
use App\Models\PropertyType;
use App\Support\TurkishText;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PropertyType::query()->get() as $type) {
            $name = TurkishText::titleCase((string) $type->name);
            if ($name !== $type->name) {
                $type->update(['name' => $name]);
            }
        }

        foreach (ListingType::query()->get() as $type) {
            $name = TurkishText::titleCase((string) $type->name);
            if ($name !== $type->name) {
                $type->update(['name' => $name]);
            }
        }
    }

    public function down(): void
    {
        // İsim formatı geri alınamaz.
    }
};
