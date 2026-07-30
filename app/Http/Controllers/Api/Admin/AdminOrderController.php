<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    /**
     * GET /api/admin/orders
     * Filters: status, search (order_number ya customer name), date_from, date_to, sort, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('user:id,name,email,phone')->withCount('items');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        match ($request->query('sort')) {
            'total_desc' => $query->orderByDesc('total'),
            'oldest'     => $query->oldest(),
            default      => $query->latest(),
        };

        return response()->json([
            'success' => true,
            'data'    => ['orders' => $query->paginate($request->integer('per_page', 15))],
        ]);
    }

    /**
     * GET /api/admin/orders/{order} — poori detail (items + address + customer).
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['order' => $order->load('items', 'address', 'user:id,name,email,phone')],
        ]);
    }

    /**
     * PATCH /api/admin/orders/{order}/status
     * Body: { "status": "confirmed" }
     *
     * IMPORTANT: cancel karne par stock wapas add ho jata hai.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,processing,delivered,cancelled'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $order->status;
        $newStatus = $data['status'];

        // Delivered ya cancelled order ka status dobara change nahi hona chahiye
        if (in_array($oldStatus, ['delivered', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Ye order pehle se '{$oldStatus}' hai, status change nahi ho sakta.",
            ], 422);
        }

        DB::transaction(function () use ($order, $newStatus, $oldStatus, $data) {
            // Cancel hone par stock wapas
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    }
                }
            }

            $order->update([
                'status' => $newStatus,
                'notes'  => $data['notes'] ?? $order->notes,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Order status updated to '{$newStatus}'.",
            'data'    => ['order' => $order->fresh()->load('items')],
        ]);
    }

    /**
     * GET /api/admin/orders/stats/summary
     * Orders screen ke upar wale counters ke liye.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total'      => Order::count(),
                'pending'    => Order::where('status', 'pending')->count(),
                'confirmed'  => Order::where('status', 'confirmed')->count(),
                'processing' => Order::where('status', 'processing')->count(),
                'delivered'  => Order::where('status', 'delivered')->count(),
                'cancelled'  => Order::where('status', 'cancelled')->count(),
            ],
        ]);
    }
}
