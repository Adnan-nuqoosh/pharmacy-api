<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Equipment;
use App\Models\EquipmentRental;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentController extends Controller
{
    /**
     * GET /api/equipment  (public)
     * Design ka "Rent Medical Equipment".
     */
    public function index(Request $request): JsonResponse
    {
        $query = Equipment::where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        match ($request->query('sort')) {
            'rent_asc'  => $query->orderBy('rent_per_day'),
            'rent_desc' => $query->orderByDesc('rent_per_day'),
            default     => $query->latest(),
        };

        return response()->json([
            'success' => true,
            'data'    => ['equipment' => $query->paginate($request->integer('per_page', 12))],
        ]);
    }

    /**
     * GET /api/equipment/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $equipment = Equipment::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json(['success' => true, 'data' => ['equipment' => $equipment]]);
    }

    /**
     * POST /api/equipment/rentals  (login required)
     * Body: { equipment_id, address_id, start_date, end_date, quantity }
     */
    public function rent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'exists:equipment,id'],
            'address_id'   => ['required', 'exists:addresses,id'],
            'start_date'   => ['required', 'date', 'after_or_equal:today'],
            'end_date'     => ['required', 'date', 'after_or_equal:start_date'],
            'quantity'     => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        // Address user ka apna hona chahiye
        $address = Address::where('id', $data['address_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $address) {
            return response()->json(['success' => false, 'message' => 'Ye address aapka nahi hai.'], 403);
        }

        $quantity = $data['quantity'] ?? 1;
        $start    = \Carbon\Carbon::parse($data['start_date']);
        $end      = \Carbon\Carbon::parse($data['end_date']);
        $days     = $start->diffInDays($end) + 1; // dono din shamil

        $rental = DB::transaction(function () use ($data, $request, $quantity, $days, $start, $end) {
            $equipment = Equipment::where('id', $data['equipment_id'])->lockForUpdate()->first();

            if (! $equipment->is_active || $equipment->available_units < $quantity) {
                return null;
            }

            $rentTotal = $equipment->rent_per_day * $days * $quantity;
            $deposit   = $equipment->deposit * $quantity;

            $rental = EquipmentRental::create([
                'rental_number' => 'RNT-' . strtoupper(Str::random(8)),
                'user_id'       => $request->user()->id,
                'equipment_id'  => $equipment->id,
                'address_id'    => $data['address_id'],
                'start_date'    => $start,
                'end_date'      => $end,
                'days'          => $days,
                'quantity'      => $quantity,
                'rent_total'    => round($rentTotal, 2),
                'deposit'       => round($deposit, 2),
                'total'         => round($rentTotal + $deposit, 2),
            ]);

            $equipment->decrement('available_units', $quantity);

            return $rental;
        });

        if (! $rental) {
            return response()->json([
                'success' => false,
                'message' => 'Itne units available nahi hain.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rental request submit ho gayi!',
            'data'    => ['rental' => $rental->load('equipment', 'address')],
        ], 201);
    }

    /**
     * GET /api/equipment/rentals/my — meri rentals.
     */
    public function myRentals(Request $request): JsonResponse
    {
        $rentals = EquipmentRental::with('equipment:id,name,slug,image', 'address')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json(['success' => true, 'data' => ['rentals' => $rentals]]);
    }

    /**
     * PATCH /api/equipment/rentals/{rental}/cancel
     * Units wapas available ho jate hain.
     */
    public function cancelRental(Request $request, EquipmentRental $rental): JsonResponse
    {
        abort_if($rental->user_id !== $request->user()->id, 403, 'Ye rental aapka nahi hai.');

        if (! in_array($rental->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => "Ye rental '{$rental->status}' hai, cancel nahi ho sakta.",
            ], 422);
        }

        DB::transaction(function () use ($rental) {
            $rental->update(['status' => 'cancelled']);
            $rental->equipment->increment('available_units', $rental->quantity);
        });

        return response()->json([
            'success' => true,
            'message' => 'Rental cancel ho gaya.',
            'data'    => ['rental' => $rental->fresh()],
        ]);
    }
}
