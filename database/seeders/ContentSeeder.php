<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorSlot;
use App\Models\Equipment;
use App\Models\Faq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    /**
     * Doctors + slots, Equipment, FAQs ka sample data.
     * Chalayein: php artisan db:seed --class=ContentSeeder
     */
    public function run(): void
    {
        // ---------- Doctors ----------
        $doctors = [
            ['Dr. Ahmed Al Mansoori', 'General Physician', 'MBBS, MD', 12, 150],
            ['Dr. Sara Khalid',       'Dermatologist',     'MBBS, FCPS (Dermatology)', 8, 250],
            ['Dr. Imran Haider',      'Pediatrician',      'MBBS, DCH', 15, 200],
            ['Dr. Fatima Noor',       'Gynecologist',      'MBBS, FCPS', 10, 300],
            ['Dr. Yousuf Rahman',     'Cardiologist',      'MBBS, FCPS (Cardiology)', 20, 400],
        ];

        foreach ($doctors as [$name, $speciality, $qualification, $years, $fee]) {
            $doctor = Doctor::firstOrCreate(
                ['name' => $name],
                [
                    'speciality'       => $speciality,
                    'qualification'    => $qualification,
                    'experience_years' => $years,
                    'consultation_fee' => $fee,
                    'rating'           => 4.5,
                    'about'            => "{$name} is an experienced {$speciality} with {$years} years of practice.",
                ]
            );

            // Agle 7 din ke slots (10:00 se 13:00, har 30 min)
            for ($d = 1; $d <= 7; $d++) {
                $date = now()->addDays($d)->format('Y-m-d');

                for ($h = 10; $h < 13; $h++) {
                    foreach ([0, 30] as $min) {
                        $start = sprintf('%02d:%02d', $h, $min);
                        $end   = $min === 0 ? sprintf('%02d:30', $h) : sprintf('%02d:00', $h + 1);

                        DoctorSlot::firstOrCreate([
                            'doctor_id'  => $doctor->id,
                            'date'       => $date,
                            'start_time' => $start,
                        ], [
                            'end_time' => $end,
                        ]);
                    }
                }
            }
        }

        // ---------- Equipment ----------
        $equipment = [
            ['Wheelchair (Standard)',        25,  200, 10],
            ['Hospital Bed (Electric)',      120, 800, 5],
            ['Oxygen Concentrator 5L',       90,  1000, 8],
            ['Nebulizer Machine',            15,  150, 15],
            ['Patient Lift / Hoist',         100, 700, 3],
            ['Walker with Wheels',           12,  100, 20],
        ];

        foreach ($equipment as [$name, $rent, $deposit, $units]) {
            Equipment::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name'            => $name,
                    'description'     => "{$name} available for rent. Delivered and collected at your address.",
                    'rent_per_day'    => $rent,
                    'deposit'         => $deposit,
                    'available_units' => $units,
                ]
            );
        }

        // ---------- FAQs ----------
        $faqs = [
            ['Orders', 'How do I place an order?', 'Browse products, add them to your cart, select a delivery address and confirm at checkout.'],
            ['Orders', 'Can I cancel my order?', 'Yes, orders can be cancelled while they are Pending or Confirmed from the My Orders screen.'],
            ['Delivery', 'What are the delivery charges?', 'Delivery is free on orders above AED 100. Below that a flat AED 10 fee applies.'],
            ['Delivery', 'How long does delivery take?', 'Standard delivery within Dubai takes 1-2 working days.'],
            ['Prescriptions', 'Do I need a prescription?', 'Prescription medicines require a valid UAE prescription along with your Emirates ID. For other items you can simply tell us what you need.'],
            ['Prescriptions', 'Why do you need my Emirates ID?', 'UAE regulations require pharmacies to verify identity before dispensing prescription medicines.'],
            ['Payments', 'What payment methods do you accept?', 'We accept Cash/Card on Delivery and Online Payment.'],
            ['Returns', 'What is your return policy?', 'Unopened non-prescription products can be returned within 7 days of delivery.'],
        ];

        foreach ($faqs as $i => [$category, $question, $answer]) {
            Faq::firstOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'category' => $category, 'sort_order' => $i + 1]
            );
        }
    }
}
