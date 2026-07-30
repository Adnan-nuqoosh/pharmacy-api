<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard/stats
     * Dashboard ke stat cards ka poora data.
     */
    public function stats(): JsonResponse
    {
        $deliveredRevenue = Order::where('status', 'delivered')->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [
                    'today'   => Order::whereDate('created_at', today())->count(),
                    'week'    => Order::where('created_at', '>=', now()->subDays(7))->count(),
                    'month'   => Order::where('created_at', '>=', now()->startOfMonth())->count(),
                    'total'   => Order::count(),
                    'pending' => Order::where('status', 'pending')->count(),
                ],
                'revenue' => [
                    'today'     => round(Order::whereDate('created_at', today())->where('status', '!=', 'cancelled')->sum('total'), 2),
                    'month'     => round(Order::where('created_at', '>=', now()->startOfMonth())->where('status', '!=', 'cancelled')->sum('total'), 2),
                    'delivered' => round($deliveredRevenue, 2),
                ],
                'products' => [
                    'total'      => Product::count(),
                    'active'     => Product::where('is_active', true)->count(),
                    'low_stock'  => Product::where('stock', '<', 10)->count(),
                    'out_of_stock' => Product::where('stock', 0)->count(),
                ],
                'prescriptions' => [
                    'pending'  => Prescription::where('status', 'pending')->count(),
                    'approved' => Prescription::where('status', 'approved')->count(),
                    'rejected' => Prescription::where('status', 'rejected')->count(),
                ],
                'customers' => [
                    'total' => User::where('is_admin', false)->count(),
                    'new_this_month' => User::where('is_admin', false)
                        ->where('created_at', '>=', now()->startOfMonth())->count(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/sales-chart?days=7
     * Chart ke liye din ba din sales data.
     */
    public function salesChart(Request $request): JsonResponse
    {
        $days = min($request->integer('days', 7), 90);

        $rows = Order::where('created_at', '>=', now()->subDays($days))
            ->where('status', '!=', 'cancelled')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ['chart' => $rows],
        ]);
    }

    /**
     * GET /api/admin/dashboard/top-products?limit=10
     * Sab se zyada bikne wale products.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 10), 50);

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.line_total) as total_revenue')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ['products' => $rows],
        ]);
    }

    /**
     * GET /api/admin/dashboard/recent-orders?limit=10
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $orders = Order::with('user:id,name')
            ->latest()
            ->limit(min($request->integer('limit', 10), 50))
            ->get();

        return response()->json(['success' => true, 'data' => ['orders' => $orders]]);
    }

    /**
     * GET /api/admin/users
     * Customers list. Filters: search, is_admin, per_page
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::withCount('orders' /* ye relation User model mein add karna hoga */);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_admin')) {
            $query->where('is_admin', $request->boolean('is_admin'));
        }

        return response()->json([
            'success' => true,
            'data'    => ['users' => $query->latest()->paginate($request->integer('per_page', 15))],
        ]);
    }

    /**
     * GET /api/admin/users/{user}
     * Customer detail + uske orders.
     */
    public function userDetail(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user'   => $user,
                'orders' => $user->orders()->latest()->limit(20)->get(),
                'stats'  => [
                    'total_orders' => $user->orders()->count(),
                    'total_spent'  => round($user->orders()->where('status', '!=', 'cancelled')->sum('total'), 2),
                ],
            ],
        ]);
    }
}
