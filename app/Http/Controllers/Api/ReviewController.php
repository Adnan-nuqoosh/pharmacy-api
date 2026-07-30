<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * GET /api/products/{slug}/reviews
     * Product Details screen ka reviews section.
     * Response mein summary bhi: average rating, total, star breakdown (%).
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $query = $product->reviews()->with('user:id,name')->where('is_approved', true);

        // Filter: sirf 5-star waale, etc.
        if ($star = $request->integer('star')) {
            $query->where('rating', $star);
        }

        match ($request->query('sort')) {
            'highest' => $query->orderByDesc('rating'),
            'lowest'  => $query->orderBy('rating'),
            default   => $query->latest(),
        };

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'average_rating' => (float) $product->rating,
                    'total_reviews'  => $product->reviews_count,
                    'breakdown'      => $product->ratingBreakdown(),
                ],
                'reviews' => $query->paginate($request->integer('per_page', 10)),
            ],
        ]);
    }

    /**
     * POST /api/products/{slug}/reviews   (login required)
     * Body: { "rating": 5, "comment": "Bohot acha product" }
     *
     * Rule: sirf wohi user review kar sakta hai jisne ye product khareeda ho
     * (verified reviews — fake reviews rokne ke liye).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::where('slug', $slug)->firstOrFail();
        $userId  = $request->user()->id;

        // Kya user ne ye product kabhi khareeda hai?
        $hasPurchased = Order::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        if (! $hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'Aap sirf khareeday hue products par review kar sakte hain.',
            ], 403);
        }

        // Pehle se review diya hua hai?
        if (Review::where('product_id', $product->id)->where('user_id', $userId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Aap is product par pehle hi review de chuke hain. Update karne ke liye PATCH use karein.',
            ], 422);
        }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id'    => $userId,
            'rating'     => $data['rating'],
            'comment'    => $data['comment'] ?? null,
        ]);

        $product->recalculateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review submit ho gaya. Shukriya!',
            'data'    => ['review' => $review->load('user:id,name')],
        ], 201);
    }

    /**
     * PATCH /api/reviews/{review} — apna review update karna.
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        abort_if($review->user_id !== $request->user()->id, 403, 'Ye review aapka nahi hai.');

        $data = $request->validate([
            'rating'  => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update($data);
        $review->product->recalculateRating();

        return response()->json([
            'success' => true,
            'message' => 'Review updated.',
            'data'    => ['review' => $review->fresh()->load('user:id,name')],
        ]);
    }

    /**
     * DELETE /api/reviews/{review} — apna review delete karna.
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        abort_if($review->user_id !== $request->user()->id, 403, 'Ye review aapka nahi hai.');

        $product = $review->product;
        $review->delete();
        $product->recalculateRating();

        return response()->json(['success' => true, 'message' => 'Review deleted.']);
    }

    /**
     * GET /api/my-reviews — user ke apne saare reviews.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $reviews = Review::with('product:id,name,slug,image')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json(['success' => true, 'data' => ['reviews' => $reviews]]);
    }
}
