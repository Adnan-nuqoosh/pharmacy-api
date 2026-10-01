<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\CatalogImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Brand::withCount('products')->orderBy('sort_order')->orderBy('name');
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->query('search').'%');
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json(['success' => true, 'data' => ['brands' => $query->get()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $brand = CatalogImage::save(new Brand, $request, $data, 'brands', 'image', ['logo']);

        return response()->json(['success' => true, 'message' => 'Brand created.', 'data' => ['brand' => $brand]], 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['brand' => $brand->loadCount('products')]]);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        CatalogImage::save($brand, $request, $this->validated($request, $brand), 'brands', 'image', ['logo']);

        return response()->json(['success' => true, 'message' => 'Brand updated.', 'data' => ['brand' => $brand]]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return response()->json(['success' => false, 'message' => 'Reassign or unlink this brand’s products before deleting it.', 'data' => null, 'errors' => []], 422);
        }
        $path = $brand->image;
        $brand->delete();
        CatalogImage::delete($path, 'brands');

        return response()->json(['success' => true, 'message' => 'Brand deleted.']);
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        if (! $brand && ! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('name', ''))]);
        }

        return $request->validate([
            'name' => [$brand ? 'sometimes' : 'required', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'required', 'string', 'max:170', Rule::unique('brands')->ignore($brand?->id)],
            'image' => CatalogImage::rules(),
            'logo' => CatalogImage::rules(),
            'remove_image' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
