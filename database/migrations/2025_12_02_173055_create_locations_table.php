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
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID as primary key
            $table->string('name')->nullable(); // optional: for labeling
            $table->decimal('latitude', 10, 8);  // e.g., -90.00000000 to 90.00000000
            $table->decimal('longitude', 11, 8); // e.g., -180.00000000 to 180.00000000
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
