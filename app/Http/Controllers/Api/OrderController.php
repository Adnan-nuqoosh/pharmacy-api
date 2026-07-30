<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * POST /api/checkout — Checkout screen.
     * Design ke mutabiq payment_method: cod | online
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address_id'     => ['required', 'exists:addresses,id'],
            'payment_method' => ['sometimes', 'in:cod,online'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $address = Address::where('id', $data['address_id'])->where('user_id', $user->id)->first();
        if (! $address) {
            return response()->json(['success' => false, 'message' => 'Ye address aapka nahi hai.'], 403);
        }

        $cartItems = CartItem::with('product')->where('user_id', $user->id)->get();
        if ($cartItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Cart khali hai.'], 422);
        }

        foreach ($cartItems as $item) {
            if (! $item->product->is_active || $item->product->stock < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "'{$item->product->name}' ka stock kafi nahi hai.",
                ], 422);
            }
        }

        $subtotal = 0.0;
        $discount = 0.0;
        foreach ($cartItems as $item) {
            $subtotal += $item->product->price * $item->quantity;
            $discount += ($item->product->price - $item->product->final_price) * $item->quantity;
        }
        $afterDiscount = $subtotal - $discount;
        $deliveryFee   = $afterDiscount >= 100 ? 0 : 10;
        $total         = $afterDiscount + $deliveryFee;

        $order = DB::transaction(function () use ($user, $address, $cartItems, $subtotal, $discount, $deliveryFee, $total, $data) {
            $order = Order::create([
                'order_number'   => 'ORD-' . strtoupper(Str::random(8)),
                'user_id'        => $user->id,
                'address_id'     => $address->id,
                'subtotal'       => round($subtotal, 2),
                'discount'       => round($discount, 2),
                'delivery_fee'   => round($deliveryFee, 2),
                'total'          => round($total, 2),
                'payment_method' => $data['payment_method'] ?? 'cod',
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($cartItems as $item) {
                $p = $item->product;
                $order->items()->create([
                    'product_id'       => $p->id,
                    'product_name'     => $p->name,
                    'price'            => $p->price,
                    'discount_percent' => $p->discount_percent,
                    'final_price'      => $p->final_price,
                    'quantity'         => $item->quantity,
                    'line_total'       => round($p->final_price * $item->quantity, 2),
                ]);

                $p->decrement('stock', $item->quantity);
            }

            CartItem::where('user_id', $user->id)->delete();

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully!',
            'data'    => ['order' => $order->load('items', 'address')],
        ], 201);
    }

    /**
     * GET /api/orders — My Orders screen.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::withCount('items')->where('user_id', $request->user()->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data'    => ['orders' => $query->latest()->paginate($request->integer('per_page', 10))],
        ]);
    }

    /**
     * GET /api/orders/{order} — order detail.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Ye order aapka nahi hai.');

        return response()->json([
            'success' => true,
            'data'    => ['order' => $order->load('items', 'address')],
        ]);
    }

    /**
     * PATCH /api/orders/{order}/cancel
     * Customer khud apna order cancel kar sakta hai (sirf pending/confirmed).
     * Stock wapas add ho jata hai.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Ye order aapka nahi hai.');

        if (! in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => "Ye order '{$order->status}' hai, ab cancel nahi ho sakta. Pharmacy se raabta karein.",
            ], 422);
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Order cancel ho gaya.',
            'data'    => ['order' => $order->fresh()->load('items')],
        ]);
    }

    /**
     * GET /api/billing — Profile screen ka "Billing" section.
     * Kharch ka khulasa + invoices (orders) ki list.
     */
    public function billing(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $orders = Order::where('user_id', $userId)->where('status', '!=', 'cancelled');

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_orders'    => (clone $orders)->count(),
                    'total_spent'     => round((clone $orders)->sum('total'), 2),
                    'delivered_count' => (clone $orders)->where('status', 'delivered')->count(),
                    'pending_count'   => (clone $orders)->where('status', 'pending')->count(),
                ],
                'invoices' => Order::where('user_id', $userId)
                    ->select('id', 'order_number', 'total', 'payment_method', 'status', 'created_at')
                    ->latest()->paginate($request->integer('per_page', 15)),
            ],
        ]);
    }
}
