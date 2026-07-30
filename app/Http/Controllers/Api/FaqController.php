<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * GET /api/faqs  (public)
     * Profile screen ka "Faq" section. Category ke hisaab se group ho kar aata hai.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Faq::where('is_active', true);

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                  ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $faqs = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'faqs'       => $faqs,
                'grouped'    => $faqs->groupBy('category'),
                'categories' => $faqs->pluck('category')->unique()->filter()->values(),
            ],
        ]);
    }
}
