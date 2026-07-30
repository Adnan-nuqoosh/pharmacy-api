<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{
    /**
     * GET /api/admin/banners — inactive bhi shamil.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['banners' => Banner::orderBy('sort_order')->get()],
        ]);
    }

    /**
     * POST /api/admin/banners
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, isUpdate: false);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner created.',
            'data'    => ['banner' => $banner],
        ], 201);
    }

    /**
     * GET /api/admin/banners/{banner}
     */
    public function show(Banner $banner): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['banner' => $banner]]);
    }

    /**
     * POST/PATCH /api/admin/banners/{banner}
     */
    public function update(Request $request, Banner $banner): JsonResponse
    {
        $data = $this->validated($request, isUpdate: true);

        if ($request->hasFile('image')) {
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated.',
            'data'    => ['banner' => $banner->fresh()],
        ]);
    }

    /**
     * DELETE /api/admin/banners/{banner}
     */
    public function destroy(Banner $banner): JsonResponse
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return response()->json(['success' => true, 'message' => 'Banner deleted.']);
    }

    // ---------- helpers ----------

    private function validated(Request $request, bool $isUpdate): array
    {
        $req = $isUpdate ? 'sometimes|required' : 'required';

        return $request->validate([
            'title'      => [$req, 'string', 'max:255'],
            'subtitle'   => ['nullable', 'string', 'max:255'],
            'image'      => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'link'       => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);
    }
}
