<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relasi ke User

            // --- Sesuai Form UI "Isi Data Lapak" ---
            $table->string('name'); // "Nama Lapak / Usaha"
            $table->text('description')->nullable(); // "Deskripsi Lapak"
            $table->string('whatsapp_number'); // "Nomor Whatsapp"
            $table->string('category'); // "Kategori Jualan Utama" (Bakso, Sate, dll)
            $table->string('profile_image')->nullable(); // "Foto Profile"
            $table->string('cart_image')->nullable(); // "Foto Gerobak (Opsional)"

            // --- Untuk Fitur Live Tracking ---
            $table->boolean('is_live')->default(false); // Status Online/Offline
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
