<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    /**
     * GET /api/admin/products
     * Admin list — inactive products bhi dikhte hain (customer API mein nahi dikhte).
     * Filters: search, category_id, is_active, low_stock, sort, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name,slug');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('low_stock')) {
            $query->where('stock', '<', 10);
        }

        match ($request->query('sort')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'stock_asc'  => $query->orderBy('stock'),
            default      => $query->latest(),
        };

        return response()->json([
            'success' => true,
            'data'    => ['products' => $query->paginate($request->integer('per_page', 15))],
        ]);
    }

    /**
     * POST /api/admin/products
     * multipart/form-data (image ke sath) ya JSON (image ke baghair)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, isUpdate: false);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Product created.',
            'data'    => ['product' => $product->load('category:id,name,slug')],
        ], 201);
    }

    /**
     * GET /api/admin/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['product' => $product->load('category:id,name,slug')],
        ]);
    }

    /**
     * POST /api/admin/products/{product}  (with _method=PUT for file upload)
     * ya PATCH /api/admin/products/{product} (JSON only)
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request, isUpdate: true, productId: $product->id);

        if ($request->hasFile('image')) {
            // Purani image delete
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Product updated.',
            'data'    => ['product' => $product->fresh()->load('category:id,name,slug')],
        ]);
    }

    /**
     * DELETE /api/admin/products/{product}
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    /**
     * PATCH /api/admin/products/{product}/toggle-active
     * Quick on/off — list screen ke toggle switch ke liye.
     */
    public function toggleActive(Product $product): JsonResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return response()->json([
            'success' => true,
            'message' => $product->is_active ? 'Product activated.' : 'Product deactivated.',
            'data'    => ['product' => $product],
        ]);
    }

    // ---------- helpers ----------

    private function validated(Request $request, bool $isUpdate, ?int $productId = null): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return $request->validate([
            'category_id'      => [$req, 'exists:categories,id'],
            'name'             => [$req, 'string', 'max:255'],
            'slug'             => ['sometimes', 'string', 'max:255', Rule::unique('products')->ignore($productId)],
            'description'      => ['nullable', 'string'],
            'price'            => [$req, 'numeric', 'min:0'],
            'discount_percent' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'image'            => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rating'           => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'stock'            => ['sometimes', 'integer', 'min:0'],
            'is_featured'      => ['sometimes', 'boolean'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);
    }
}
