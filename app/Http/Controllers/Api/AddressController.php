<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * GET /api/addresses — user ke saved addresses.
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => ['addresses' => $addresses]]);
    }

    /**
     * POST /api/addresses — naya address (sab required fields zaroori).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, isUpdate: false);

        $data['user_id'] = $request->user()->id;

        if (! Address::where('user_id', $data['user_id'])->exists()) {
            $data['is_default'] = true;
        }

        $address = Address::create($data);
        $this->syncDefault($request, $address);

        return response()->json([
            'success' => true,
            'message' => 'Address saved.',
            'data'    => ['address' => $address],
        ], 201);
    }

    /**
     * PATCH /api/addresses/{address} — PARTIAL update (sirf change hone wale fields bhejein).
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        $address->update($this->validated($request, isUpdate: true));
        $this->syncDefault($request, $address->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Address updated.',
            'data'    => ['address' => $address->fresh()],
        ]);
    }

    /**
     * DELETE /api/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            Address::where('user_id', $request->user()->id)
                ->latest()
                ->first()?->update(['is_default' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Address deleted.']);
    }

    // ---------- helpers ----------

    /**
     * store  => sab fields required
     * update => sab fields "sometimes|required" (bheji jaye to zaroori, na bheje to skip)
     *
     * FIX: $req ek single array hai (dead code aur duplicate keys hata diye).
     * Array mein spread (...$req) use karte hain taake 'sometimes' aur 'required'
     * dono rules ek saath lag jayein jab update ho.
     */
    private function validated(Request $request, bool $isUpdate): array
    {
        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'label'      => ['sometimes', 'string', 'max:50'],
            'name'       => [...$req, 'string', 'max:100'],
            'phone'      => [...$req, 'string', 'max:20'],
            'city'       => [...$req, 'string', 'max:100'],
            'area'       => ['nullable', 'string', 'max:150'],
            'street'     => [...$req, 'string', 'max:200'],
            'building'   => ['nullable', 'string', 'max:200'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        abort_if($address->user_id !== $request->user()->id, 403, 'Ye address aapka nahi hai.');
    }

    private function syncDefault(Request $request, Address $address): void
    {
        if ($address->is_default) {
            Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }
    }
}