<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * Query params (sab optional):
     *   search=shampoo            -> naam/description mein search (design ki search bar)
     *   category=baby-care        -> category slug se filter
     *   featured=1                -> sirf featured products (home sections)
     *   on_sale=1                 -> sirf discount wale products
     *   sort=price_asc|price_desc|rating|latest (default: latest)
     *   per_page=12               -> pagination size
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('category:id,name,slug,icon', 'brand')
            ->where('is_active', true);

        // Search (design ki "Search Medicine & Healthcare Products" bar)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter (slug se)
        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        // Featured / Sale filters
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($request->boolean('on_sale')) {
            $query->where('discount_percent', '>', 0);
        }

        // Sorting
        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->latest(),
        };

        $products = $query->paginate($request->integer('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => ['products' => $products],
        ]);
    }

    /**
     * GET /api/products/{slug}
     * Product Details screen ke liye single product.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::with('category:id,name,slug,icon', 'brand')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Related products (same category, current wala chor kar)
        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->take(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'related' => $related,
            ],
        ]);
    }
}
