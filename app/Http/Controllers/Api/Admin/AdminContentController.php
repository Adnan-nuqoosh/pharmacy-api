<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSlot;
use App\Models\Equipment;
use App\Models\EquipmentRental;
use App\Models\Faq;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminContentController extends Controller
{
    // ==================== DOCTORS ====================

    public function doctors(Request $request): JsonResponse
    {
        $query = Doctor::withCount('appointments');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data'    => ['doctors' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    public function storeDoctor(Request $request): JsonResponse
    {
        $data = $this->doctorRules($request, false);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('doctors', 'public');
        }

        return response()->json([
            'success' => true,
            'message' => 'Doctor added.',
            'data'    => ['doctor' => Doctor::create($data)],
        ], 201);
    }

    public function updateDoctor(Request $request, Doctor $doctor): JsonResponse
    {
        $data = $this->doctorRules($request, true);

        if ($request->hasFile('image')) {
            if ($doctor->image) {
                Storage::disk('public')->delete($doctor->image);
            }
            $data['image'] = $request->file('image')->store('doctors', 'public');
        }

        $doctor->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Doctor updated.',
            'data'    => ['doctor' => $doctor->fresh()],
        ]);
    }

    public function destroyDoctor(Doctor $doctor): JsonResponse
    {
        if ($doctor->image) {
            Storage::disk('public')->delete($doctor->image);
        }

        $doctor->delete();

        return response()->json(['success' => true, 'message' => 'Doctor deleted.']);
    }

    /**
     * POST /api/admin/doctors/{doctor}/slots
     * Body: { "date": "2026-08-05", "slots": [{"start_time":"09:00","end_time":"09:30"}] }
     */
    public function createSlots(Request $request, Doctor $doctor): JsonResponse
    {
        $data = $request->validate([
            'date'                => ['required', 'date', 'after_or_equal:today'],
            'slots'               => ['required', 'array', 'min:1'],
            'slots.*.start_time'  => ['required', 'date_format:H:i'],
            'slots.*.end_time'    => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
        ]);

        $created = 0;
        foreach ($data['slots'] as $slot) {
            $exists = DoctorSlot::where('doctor_id', $doctor->id)
                ->where('date', $data['date'])
                ->where('start_time', $slot['start_time'])
                ->exists();

            if (! $exists) {
                DoctorSlot::create([
                    'doctor_id'  => $doctor->id,
                    'date'       => $data['date'],
                    'start_time' => $slot['start_time'],
                    'end_time'   => $slot['end_time'],
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$created} slots create hue.",
        ], 201);
    }

    public function deleteSlot(DoctorSlot $slot): JsonResponse
    {
        if ($slot->is_booked) {
            return response()->json([
                'success' => false,
                'message' => 'Ye slot booked hai, delete nahi ho sakta.',
            ], 422);
        }

        $slot->delete();

        return response()->json(['success' => true, 'message' => 'Slot deleted.']);
    }

    public function appointments(Request $request): JsonResponse
    {
        $query = Appointment::with('doctor:id,name,speciality', 'user:id,name,phone');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('date', $date);
        }

        return response()->json([
            'success' => true,
            'data'    => ['appointments' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    public function updateAppointmentStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ]);

        // Cancel par slot dobara free
        if ($data['status'] === 'cancelled' && $appointment->status !== 'cancelled') {
            $appointment->slot?->update(['is_booked' => false]);
        }

        $appointment->update($data);

        return response()->json([
            'success' => true,
            'message' => "Appointment status: {$data['status']}",
            'data'    => ['appointment' => $appointment->fresh()->load('doctor')],
        ]);
    }

    // ==================== EQUIPMENT ====================

    public function equipment(Request $request): JsonResponse
    {
        $query = Equipment::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data'    => ['equipment' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $data = $this->equipmentRules($request, false);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('equipment', 'public');
        }

        return response()->json([
            'success' => true,
            'message' => 'Equipment added.',
            'data'    => ['equipment' => Equipment::create($data)],
        ], 201);
    }

    public function updateEquipment(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $this->equipmentRules($request, true, $equipment->id);

        if ($request->hasFile('image')) {
            if ($equipment->image) {
                Storage::disk('public')->delete($equipment->image);
            }
            $data['image'] = $request->file('image')->store('equipment', 'public');
        }

        $equipment->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Equipment updated.',
            'data'    => ['equipment' => $equipment->fresh()],
        ]);
    }

    public function destroyEquipment(Equipment $equipment): JsonResponse
    {
        if ($equipment->image) {
            Storage::disk('public')->delete($equipment->image);
        }

        $equipment->delete();

        return response()->json(['success' => true, 'message' => 'Equipment deleted.']);
    }

    public function rentals(Request $request): JsonResponse
    {
        $query = EquipmentRental::with('equipment:id,name', 'user:id,name,phone', 'address');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data'    => ['rentals' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    public function updateRentalStatus(Request $request, EquipmentRental $rental): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,active,returned,cancelled'],
        ]);

        // Cancel ya return par units wapas
        if (in_array($data['status'], ['cancelled', 'returned'])
            && ! in_array($rental->status, ['cancelled', 'returned'])) {
            $rental->equipment->increment('available_units', $rental->quantity);
        }

        $rental->update($data);

        return response()->json([
            'success' => true,
            'message' => "Rental status: {$data['status']}",
            'data'    => ['rental' => $rental->fresh()->load('equipment')],
        ]);
    }

    // ==================== FAQ ====================

    public function faqs(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['faqs' => Faq::orderBy('sort_order')->get()],
        ]);
    }

    public function storeFaq(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question'   => ['required', 'string', 'max:255'],
            'answer'     => ['required', 'string'],
            'category'   => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FAQ added.',
            'data'    => ['faq' => Faq::create($data)],
        ], 201);
    }

    public function updateFaq(Request $request, Faq $faq): JsonResponse
    {
        $data = $request->validate([
            'question'   => ['sometimes', 'required', 'string', 'max:255'],
            'answer'     => ['sometimes', 'required', 'string'],
            'category'   => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $faq->update($data);

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated.',
            'data'    => ['faq' => $faq->fresh()],
        ]);
    }

    public function destroyFaq(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['success' => true, 'message' => 'FAQ deleted.']);
    }

    // ==================== REVIEWS (moderation) ====================

    public function reviews(Request $request): JsonResponse
    {
        $query = Review::with('product:id,name,slug', 'user:id,name');

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        return response()->json([
            'success' => true,
            'data'    => ['reviews' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    /**
     * PATCH /api/admin/reviews/{review}/toggle-approve
     * Fake ya galat review chupa dein.
     */
    public function toggleApproveReview(Review $review): JsonResponse
    {
        $review->update(['is_approved' => ! $review->is_approved]);
        $review->product->recalculateRating();

        return response()->json([
            'success' => true,
            'message' => $review->is_approved ? 'Review approved.' : 'Review hidden.',
            'data'    => ['review' => $review],
        ]);
    }

    public function destroyReview(Review $review): JsonResponse
    {
        $product = $review->product;
        $review->delete();
        $product->recalculateRating();

        return response()->json(['success' => true, 'message' => 'Review deleted.']);
    }

    // ---------- validation helpers ----------

    private function doctorRules(Request $request, bool $isUpdate): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'name'             => [...$req, 'string', 'max:150'],
            'speciality'       => [...$req, 'string', 'max:150'],
            'qualification'    => ['nullable', 'string', 'max:200'],
            'about'            => ['nullable', 'string'],
            'image'            => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:70'],
            'consultation_fee' => ['sometimes', 'numeric', 'min:0'],
            'rating'           => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);
    }

    private function equipmentRules(Request $request, bool $isUpdate, ?int $id = null): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'name'            => [...$req, 'string', 'max:255'],
            'slug'            => ['sometimes', 'string', 'max:255', Rule::unique('equipment')->ignore($id)],
            'description'     => ['nullable', 'string'],
            'image'           => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rent_per_day'    => [...$req, 'numeric', 'min:0'],
            'deposit'         => ['sometimes', 'numeric', 'min:0'],
            'available_units' => ['sometimes', 'integer', 'min:0'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);
    }
}
