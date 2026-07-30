<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product Details screen ke liye:
     * - Reviews & Ratings (design: "4.2 — 923 Ratings and 257 Reviews" + star breakdown)
     * - Extra fields: Ingredients, Expiry Date, Brand Name, VAT
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');      // 1-5
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            // Ek user ek product par sirf ek review de sakta hai
            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'is_approved']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('ingredients')->nullable()->after('description');
            $table->string('brand_name', 150)->nullable()->after('ingredients');
            $table->date('expiry_date')->nullable()->after('brand_name');
            $table->boolean('vat_inclusive')->default(true)->after('price'); // "Inclusive of VAT"
            $table->unsignedInteger('reviews_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['ingredients', 'brand_name', 'expiry_date', 'vat_inclusive', 'reviews_count']);
        });
    }
};
