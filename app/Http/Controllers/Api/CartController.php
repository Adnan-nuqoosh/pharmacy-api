<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * GET /api/cart — Cart screen (items + totals).
     */
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with('product.category:id,name,slug')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'items'   => $items,
                'summary' => $this->summary($items),
            ],
        ]);
    }

    /**
     * POST /api/cart — Add to cart (product already ho to quantity barh jati hai).
     * Body: { "product_id": 1, "quantity": 2 }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['sometimes', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::where('id', $data['product_id'])->where('is_active', true)->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product available nahi hai.'], 404);
        }

        $qty = $data['quantity'] ?? 1;

        if ($product->stock < $qty) {
            return response()->json(['success' => false, 'message' => 'Itna stock available nahi hai.'], 422);
        }

        $item = CartItem::firstOrNew([
            'user_id'    => $request->user()->id,
            'product_id' => $product->id,
        ]);
        $item->quantity = min(($item->exists ? $item->quantity : 0) + $qty, 99);
        $item->save();

        return $this->index($request)->setStatusCode(201);
    }

    /**
     * PATCH /api/cart/{cartItem} — Quantity set karna (+ / - buttons).
     * Body: { "quantity": 3 }
     */
    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorizeItem($request, $cartItem);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if ($cartItem->product->stock < $data['quantity']) {
            return response()->json(['success' => false, 'message' => 'Itna stock available nahi hai.'], 422);
        }

        $cartItem->update(['quantity' => $data['quantity']]);

        return $this->index($request);
    }

    /**
     * DELETE /api/cart/{cartItem} — Item remove.
     */
    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorizeItem($request, $cartItem);
        $cartItem->delete();

        return $this->index($request);
    }

    /**
     * DELETE /api/cart — Poora cart khali.
     */
    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true, 'message' => 'Cart cleared.']);
    }

    // ---------- helpers ----------

    private function authorizeItem(Request $request, CartItem $item): void
    {
        abort_if($item->user_id !== $request->user()->id, 403, 'Ye item aapke cart ka nahi hai.');
    }

    /**
     * Cart screen ke totals: subtotal, discount, delivery, grand total.
     */
    private function summary($items): array
    {
        $subtotal = 0.0;
        $discount = 0.0;

        foreach ($items as $item) {
            $subtotal += $item->product->price * $item->quantity;
            $discount += ($item->product->price - $item->product->final_price) * $item->quantity;
        }

        $afterDiscount = $subtotal - $discount;
        $deliveryFee   = $afterDiscount >= 100 ? 0 : 10; // AED 100+ par free delivery
        $total         = $afterDiscount + $deliveryFee;

        return [
            'items_count'  => $items->sum('quantity'),
            'subtotal'     => round($subtotal, 2),
            'discount'     => round($discount, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'total'        => round($total, 2),
        ];
    }
}
