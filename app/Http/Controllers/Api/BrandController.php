<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::where('is_active', true)->orderBy('sort_order')->orderBy('name')
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])->get();

        return response()->json(['success' => true, 'data' => ['brands' => $brands]]);
    }

    public function show(string $slug): JsonResponse
    {
        $brand = Brand::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $products = $brand->products()->where('is_active', true)->latest()->paginate(12);

        return response()->json(['success' => true, 'data' => ['brand' => $brand, 'products' => $products]]);
    }
}
