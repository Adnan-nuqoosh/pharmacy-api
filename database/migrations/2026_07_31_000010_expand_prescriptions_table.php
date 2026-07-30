<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prescription flow ko design ke mutabiq poora karta hai:
     * - "I have valid UAE Prescription" / "I don't have a Prescription" (2 flows)
     * - Emirates ID front/back (required)
     * - Insurance card front/back (optional)
     * - Delivery address, payment method, delivery preference
     */
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // Kaunsa flow: prescription hai ya nahi
            $table->enum('request_type', ['with_prescription', 'without_prescription'])
                  ->default('with_prescription')
                  ->after('user_id');

            // Emirates ID (UAE pharmacy ki qanooni requirement)
            $table->string('emirates_id_front')->nullable()->after('image_path');
            $table->string('emirates_id_back')->nullable()->after('emirates_id_front');

            // Insurance card (optional)
            $table->string('insurance_card_front')->nullable()->after('emirates_id_back');
            $table->string('insurance_card_back')->nullable()->after('insurance_card_front');

            // Delivery + payment (design ke "Select delivery address" / "Payment Method")
            $table->foreignId('address_id')->nullable()->after('insurance_card_back')
                  ->constrained()->nullOnDelete();
            $table->string('payment_method', 30)->default('cod')->after('address_id'); // online | cod
            $table->string('delivery_preference', 50)->nullable()->after('payment_method');
        });

        // Ek prescription mein multiple images ho sakti hain (design mein "Prescriptions" + "Upload Images")
        Schema::create('prescription_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_images');

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('address_id');
            $table->dropColumn([
                'request_type', 'emirates_id_front', 'emirates_id_back',
                'insurance_card_front', 'insurance_card_back',
                'payment_method', 'delivery_preference',
            ]);
        });
    }
};
