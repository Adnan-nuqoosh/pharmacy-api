<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DoctorController extends Controller
{
    /**
     * GET /api/doctors  (public)
     * Design ka "Doctor Appointment" + "Search Doctor. . ." search bar.
     * Filters: search, speciality, sort (fee_asc | rating | experience)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Doctor::where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('speciality', 'like', "%{$search}%");
            });
        }

        if ($speciality = $request->query('speciality')) {
            $query->where('speciality', $speciality);
        }

        match ($request->query('sort')) {
            'fee_asc'    => $query->orderBy('consultation_fee'),
            'rating'     => $query->orderByDesc('rating'),
            'experience' => $query->orderByDesc('experience_years'),
            default      => $query->orderByDesc('rating'),
        };

        return response()->json([
            'success' => true,
            'data'    => ['doctors' => $query->paginate($request->integer('per_page', 12))],
        ]);
    }

    /**
     * GET /api/doctors/specialities — filter dropdown ke liye.
     */
    public function specialities(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['specialities' => Doctor::where('is_active', true)
                ->distinct()->orderBy('speciality')->pluck('speciality')],
        ]);
    }

    /**
     * GET /api/doctors/{doctor} — profile + agle 14 din ke available slots.
     */
    public function show(Doctor $doctor): JsonResponse
    {
        $slots = $doctor->slots()
            ->where('is_booked', false)
            ->whereDate('date', '>=', today())
            ->whereDate('date', '<=', today()->addDays(14))
            ->orderBy('date')->orderBy('start_time')
            ->get()
            ->groupBy(fn ($slot) => $slot->date->format('Y-m-d'));

        return response()->json([
            'success' => true,
            'data'    => ['doctor' => $doctor, 'available_slots' => $slots],
        ]);
    }

    /**
     * POST /api/appointments  (login required)
     * Body: { doctor_slot_id, patient_name, patient_phone, reason }
     */
    public function book(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doctor_slot_id' => ['required', 'exists:doctor_slots,id'],
            'patient_name'   => ['required', 'string', 'max:150'],
            'patient_phone'  => ['required', 'string', 'max:20'],
            'reason'         => ['nullable', 'string', 'max:500'],
        ]);

        $appointment = DB::transaction(function () use ($data, $request) {
            // Lock — taake do log ek hi slot ek waqt mein book na kar lein
            $slot = DoctorSlot::where('id', $data['doctor_slot_id'])->lockForUpdate()->first();

            if ($slot->is_booked) {
                return null;
            }

            if ($slot->date->isPast()) {
                return false;
            }

            $doctor = $slot->doctor;

            $appointment = Appointment::create([
                'appointment_number' => 'APT-' . strtoupper(Str::random(8)),
                'user_id'            => $request->user()->id,
                'doctor_id'          => $doctor->id,
                'doctor_slot_id'     => $slot->id,
                'date'               => $slot->date,
                'time'               => $slot->start_time,
                'patient_name'       => $data['patient_name'],
                'patient_phone'      => $data['patient_phone'],
                'reason'             => $data['reason'] ?? null,
                'fee'                => $doctor->consultation_fee,
            ]);

            $slot->update(['is_booked' => true]);

            return $appointment;
        });

        if ($appointment === null) {
            return response()->json(['success' => false, 'message' => 'Ye slot abhi book ho chuka hai. Koi aur waqt chunein.'], 422);
        }

        if ($appointment === false) {
            return response()->json(['success' => false, 'message' => 'Ye slot guzar chuka hai.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment book ho gayi!',
            'data'    => ['appointment' => $appointment->load('doctor')],
        ], 201);
    }

    /**
     * GET /api/appointments — meri appointments.
     */
    public function myAppointments(Request $request): JsonResponse
    {
        $query = Appointment::with('doctor:id,name,speciality,image')
            ->where('user_id', $request->user()->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data'    => ['appointments' => $query->latest()->paginate($request->integer('per_page', 10))],
        ]);
    }

    /**
     * GET /api/appointments/{appointment}
     */
    public function showAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->user_id !== $request->user()->id, 403, 'Ye appointment aapki nahi hai.');

        return response()->json([
            'success' => true,
            'data'    => ['appointment' => $appointment->load('doctor')],
        ]);
    }

    /**
     * PATCH /api/appointments/{appointment}/cancel
     * Slot dobara available ho jata hai.
     */
    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->user_id !== $request->user()->id, 403, 'Ye appointment aapki nahi hai.');

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Ye appointment pehle se '{$appointment->status}' hai.",
            ], 422);
        }

        DB::transaction(function () use ($appointment) {
            $appointment->update(['status' => 'cancelled']);
            $appointment->slot?->update(['is_booked' => false]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancel ho gayi.',
            'data'    => ['appointment' => $appointment->fresh()],
        ]);
    }
}
