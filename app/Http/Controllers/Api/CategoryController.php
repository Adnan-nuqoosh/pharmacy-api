<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     * Home screen ka "Categories" section — active categories, sorted.
     */
    public function index(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ['categories' => $categories],
        ]);
    }

    /**
     * GET /api/categories/{slug}
     * Ek category + uske products (paginated) — "See All" screen ke liye.
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $products = $category->products()
            ->where('is_active', true)
            ->latest()
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category,
                'products' => $products,
            ],
        ]);
    }
}
