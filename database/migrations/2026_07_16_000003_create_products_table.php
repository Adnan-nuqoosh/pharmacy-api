<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products — design ke product cards ke mutabiq:
     * naam, AED price, discount %, rating, sale badge, image
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);                       // asal price (AED)
            $table->unsignedTinyInteger('discount_percent')->default(0); // e.g. 15 = "15% OFF" badge
            $table->string('image')->nullable();
            $table->decimal('rating', 2, 1)->default(0);           // e.g. 4.2
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_featured')->default(false);        // home screen sections ke liye
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
