<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checkout screen — delivery addresses ("Deliver to Dubai, UAE").
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->default('Home');   // Home / Office / Other
            $table->string('name', 100);                     // receiver name
            $table->string('phone', 20);
            $table->string('city', 100);                     // e.g. Dubai
            $table->string('area', 150)->nullable();
            $table->string('street', 200);
            $table->string('building', 200)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
