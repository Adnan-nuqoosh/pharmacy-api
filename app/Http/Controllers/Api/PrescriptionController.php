<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    /**
     * POST /api/prescriptions  (multipart/form-data)
     *
     * Design ke Rx Upload flow ke mutabiq. Do flows:
     *
     * A) request_type = with_prescription  ("I have valid UAE Prescription")
     *    - prescriptions[] (required)  : prescription ki images
     *    - emirates_id_front (required)
     *    - emirates_id_back  (required)
     *    - insurance_card_front / insurance_card_back (optional)
     *
     * B) request_type = without_prescription  ("I don't have a Prescription")
     *    - images[] (required) : jo chahiye uski tasveerein
     *    - notes    : "Tell us about what you are looking for?"
     *
     * Dono mein: address_id, payment_method (online|cod), delivery_preference
     */
    public function store(Request $request): JsonResponse
    {
        $type = $request->input('request_type', 'with_prescription');

        $rules = [
            'request_type'         => ['required', 'in:with_prescription,without_prescription'],
            'address_id'           => ['required', 'exists:addresses,id'],
            'payment_method'       => ['required', 'in:online,cod'],
            'delivery_preference'  => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ];

        if ($type === 'with_prescription') {
            // Prescription + Emirates ID zaroori (UAE requirement)
            $rules += [
                'prescriptions'          => ['required', 'array', 'min:1', 'max:5'],
                'prescriptions.*'        => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'emirates_id_front'      => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'emirates_id_back'       => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'insurance_card_front'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'insurance_card_back'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ];
        } else {
            // Bina prescription: sirf images + notes
            $rules += [
                'images'   => ['required', 'array', 'min:1', 'max:5'],
                'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ];
        }

        $request->validate($rules);

        // Address user ka apna hona chahiye
        $address = Address::where('id', $request->input('address_id'))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'Ye address aapka nahi hai.',
            ], 403);
        }

        $prescription = DB::transaction(function () use ($request, $type) {
            $data = [
                'user_id'             => $request->user()->id,
                'request_type'        => $type,
                'address_id'          => $request->input('address_id'),
                'payment_method'      => $request->input('payment_method'),
                'delivery_preference' => $request->input('delivery_preference'),
                'notes'               => $request->input('notes'),
            ];

            // Emirates ID + insurance cards (sirf with_prescription flow mein)
            foreach (['emirates_id_front', 'emirates_id_back', 'insurance_card_front', 'insurance_card_back'] as $field) {
                if ($request->hasFile($field)) {
                    $data[$field] = $request->file($field)->store('prescriptions/documents', 'public');
                }
            }

            $prescription = Prescription::create($data);

            // Images (dono flows — field ka naam alag hai)
            $files = $type === 'with_prescription'
                ? $request->file('prescriptions', [])
                : $request->file('images', []);

            foreach ($files as $i => $file) {
                $path = $file->store('prescriptions', 'public');

                $prescription->images()->create(['image_path' => $path]);

                // Pehli image main image_path mein bhi (purani API compatibility ke liye)
                if ($i === 0) {
                    $prescription->update(['image_path' => $path]);
                }
            }

            return $prescription;
        });

        return response()->json([
            'success' => true,
            'message' => 'Request submit ho gayi. Pharmacy team jald review karegi.',
            'data'    => ['prescription' => $prescription->load('images', 'address')],
        ], 201);
    }

    /**
     * GET /api/prescriptions — user ki requests + status.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Prescription::with('images', 'address')
            ->where('user_id', $request->user()->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('request_type')) {
            $query->where('request_type', $type);
        }

        return response()->json([
            'success' => true,
            'data'    => ['prescriptions' => $query->latest()->paginate($request->integer('per_page', 10))],
        ]);
    }

    /**
     * GET /api/prescriptions/{prescription}
     */
    public function show(Request $request, Prescription $prescription): JsonResponse
    {
        abort_if($prescription->user_id !== $request->user()->id, 403, 'Ye prescription aapki nahi hai.');

        return response()->json([
            'success' => true,
            'data'    => ['prescription' => $prescription->load('images', 'address')],
        ]);
    }

    /**
     * DELETE /api/prescriptions/{prescription}
     * Sirf pending request cancel ho sakti hai.
     */
    public function destroy(Request $request, Prescription $prescription): JsonResponse
    {
        abort_if($prescription->user_id !== $request->user()->id, 403, 'Ye prescription aapki nahi hai.');

        if ($prescription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Ye request pehle se '{$prescription->status}' hai, cancel nahi ho sakti.",
            ], 422);
        }

        $prescription->delete();

        return response()->json(['success' => true, 'message' => 'Request cancelled.']);
    }
}
