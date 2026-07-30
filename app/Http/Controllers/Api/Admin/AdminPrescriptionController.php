<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPrescriptionController extends Controller
{
    /**
     * GET /api/admin/prescriptions
     * Filters: status, request_type, payment_method, search, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Prescription::with('user:id,name,email,phone', 'images', 'address');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('request_type')) {
            $query->where('request_type', $type);
        }

        if ($payment = $request->query('payment_method')) {
            $query->where('payment_method', $payment);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
        }

        return response()->json([
            'success' => true,
            'data'    => ['prescriptions' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    /**
     * GET /api/admin/prescriptions/{prescription}
     * Poori detail: images, Emirates ID, insurance cards, address.
     */
    public function show(Prescription $prescription): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['prescription' => $prescription->load('user:id,name,email,phone', 'images', 'address')],
        ]);
    }

    /**
     * PATCH /api/admin/prescriptions/{prescription}/approve
     */
    public function approve(Request $request, Prescription $prescription): JsonResponse
    {
        $data = $request->validate([
            'admin_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($prescription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Ye prescription pehle se '{$prescription->status}' hai.",
            ], 422);
        }

        $prescription->update([
            'status'        => 'approved',
            'admin_remarks' => $data['admin_remarks'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prescription approved.',
            'data'    => ['prescription' => $prescription->fresh()->load('images')],
        ]);
    }

    /**
     * PATCH /api/admin/prescriptions/{prescription}/reject
     * admin_remarks required — customer ko wajah pata chalni chahiye.
     */
    public function reject(Request $request, Prescription $prescription): JsonResponse
    {
        $data = $request->validate([
            'admin_remarks' => ['required', 'string', 'max:500'],
        ]);

        if ($prescription->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Ye prescription pehle se '{$prescription->status}' hai.",
            ], 422);
        }

        $prescription->update([
            'status'        => 'rejected',
            'admin_remarks' => $data['admin_remarks'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prescription rejected.',
            'data'    => ['prescription' => $prescription->fresh()],
        ]);
    }

    /**
     * GET /api/admin/prescriptions/stats/summary
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total'                 => Prescription::count(),
                'pending'               => Prescription::where('status', 'pending')->count(),
                'approved'              => Prescription::where('status', 'approved')->count(),
                'rejected'              => Prescription::where('status', 'rejected')->count(),
                'with_prescription'     => Prescription::where('request_type', 'with_prescription')->count(),
                'without_prescription'  => Prescription::where('request_type', 'without_prescription')->count(),
            ],
        ]);
    }
}
