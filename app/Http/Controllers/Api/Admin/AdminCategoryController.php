<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CatalogImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    /**
     * GET /api/admin/categories — inactive bhi shamil.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::withCount('products');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => ['categories' => $query->orderBy('sort_order')->get()],
        ]);
    }

    /**
     * POST /api/admin/categories
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, isUpdate: false);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = CatalogImage::save(new Category, $request, $data, 'categories', 'icon', ['icon']);

        return response()->json([
            'success' => true,
            'message' => 'Category created.',
            'data' => ['category' => $category],
        ], 201);
    }

    /**
     * GET /api/admin/categories/{category}
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['category' => $category->loadCount('products')],
        ]);
    }

    /**
     * POST/PATCH /api/admin/categories/{category}
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validated($request, isUpdate: true, categoryId: $category->id);

        CatalogImage::save($category, $request, $data, 'categories', 'icon', ['icon']);

        return response()->json([
            'success' => true,
            'message' => 'Category updated.',
            'data' => ['category' => $category->fresh()],
        ]);
    }

    /**
     * DELETE /api/admin/categories/{category}
     * SAFETY: agar category mein products hain to delete nahi hogi
     * (warna products bhi cascade delete ho jayenge!)
     */
    public function destroy(Category $category): JsonResponse
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Is category mein {$productCount} products hain. Pehle unhe kisi aur category mein move karein ya delete karein.",
            ], 422);
        }

        $path = $category->icon;
        $category->delete();
        CatalogImage::delete($path, 'categories');

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    /**
     * POST /api/admin/categories/reorder
     * Body: { "order": [{"id": 3, "sort_order": 1}, {"id": 1, "sort_order": 2}] }
     * Drag-drop reorder ke liye.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'exists:categories,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['order'] as $row) {
            Category::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
        }

        return response()->json(['success' => true, 'message' => 'Order updated.']);
    }

    // ---------- helpers ----------

    private function validated(Request $request, bool $isUpdate, ?int $categoryId = null): array
    {
        $req = $isUpdate ? 'sometimes' : 'required';

        if (! $isUpdate && ! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('name', ''))]);
        }

        return $request->validate([
            'name' => [$req, 'required', 'string', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('categories')->ignore($categoryId)],
            'image' => CatalogImage::rules(),
            'remove_image' => ['sometimes', 'boolean'],
            'icon' => CatalogImage::rules(),
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
