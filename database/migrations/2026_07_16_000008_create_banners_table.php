<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Home screen ke offer banners ("Up to 50% OFF" slider).
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');                 // e.g. "SUMMER SAVERS"
            $table->string('subtitle')->nullable();  // e.g. "Up to 50% OFF on Vitamins & Beauty essentials"
            $table->string('image')->nullable();
            $table->string('link')->nullable();      // e.g. /products?on_sale=1
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
