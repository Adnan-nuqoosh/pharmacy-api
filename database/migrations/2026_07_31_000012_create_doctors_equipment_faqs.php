<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Home screen ke baqi 2 buttons + Profile screen ka FAQ:
     * - "Doctor Appointment"  (+ "Search Doctor..." bar)
     * - "Rent Medical Equipment"
     * - FAQ
     */
    public function up(): void
    {
        // ---- Doctors ----
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('speciality', 150);           // e.g. Dermatologist
            $table->string('qualification', 200)->nullable();
            $table->string('image')->nullable();
            $table->text('about')->nullable();
            $table->unsignedInteger('experience_years')->default(0);
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->decimal('rating', 2, 1)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Doctor ke available slots
        Schema::create('doctor_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_booked')->default(false);
            $table->timestamps();

            $table->unique(['doctor_id', 'date', 'start_time']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_number', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('time');
            $table->string('patient_name', 150);
            $table->string('patient_phone', 20);
            $table->text('reason')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // ---- Medical Equipment Rental ----
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('rent_per_day', 10, 2);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->unsignedInteger('available_units')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment_rentals', function (Blueprint $table) {
            $table->id();
            $table->string('rental_number', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('rent_total', 10, 2);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'active', 'returned', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // ---- FAQ (Profile screen) ----
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 100)->nullable();  // e.g. Orders, Delivery, Payments
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('equipment_rentals');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctor_slots');
        Schema::dropIfExists('doctors');
    }
};
