<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with([
            'bakery:id,name,user_id',
            'discountEvent:id,discount_name,discount,discount_start_time,discount_end_time'
        ])
            ->orderByDesc('id')
            ->get();

        return response()->json($products, 200);
        // return response()->json([
        //     'success' => true,
        //     'message' => 'Product list retrieved successfully',
        //     'data'    => $products
        // ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'bakery_id'      => ['required', 'integer', 'exists:bakeries,id'],
            'product_name'   => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string'],
            'price'          => ['required', 'integer', 'min:0'],
            'best_before'    => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'image_url'      => ['nullable', 'url'],
            'discount_price' => ['nullable', 'integer', 'min:0'],
            'discount_id'    => ['nullable', 'integer', 'exists:discount_events,id'],
        ]);

        // pastikan user adalah owner bakery atau admin
        $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $data['bakery_id']);

        // aturan kecil: kalau ada discount_price, harus < price
        if (isset($data['discount_price']) && $data['discount_price'] >= $data['price']) {
            return response()->json(['message' => 'discount_price must be less than price'], 422);
        }

        $product = Product::create($data)->load(['bakery', 'discountEvent']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data'    => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        // $product->load([
        //     'bakery:id,name,user_id',
        //     'discountEvent:id,discount_name,discount,discount_start_time,discount_end_time'
        // ]);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data'    => $product
        ], 200);
    }

    // get products by bakery id
    public function getProductsByBakery($id)
    {
        $products = Product::where('bakery_id', $id)
            ->orderByDesc('id')->get();

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No products found for this bakery',
                'products' => []
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved',
            'products' => $products
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::with('bakery:id,user_id')->findOrFail($id);

        $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $product->bakery_id);

        $data = $request->validate([
            'product_name'   => ['sometimes', 'string', 'max:150'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'price'          => ['sometimes', 'integer', 'min:0'],
            'best_before'    => ['sometimes', 'nullable', 'integer', Rule::in([1, 2, 3])],
            'image_url'      => ['sometimes', 'nullable', 'url'],
            'discount_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'discount_id'    => ['sometimes', 'nullable', 'integer', 'exists:discount_events,id'],
            // pindah bakery (optional)
            'bakery_id'      => ['sometimes', 'integer', 'exists:bakeries,id'],
        ]);

        // Kalau mau pindahkan product ke bakery lain → cek owner bakery baru
        if (array_key_exists('bakery_id', $data)) {
            $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $data['bakery_id']);
        }

        // validasi diskon < price jika keduanya ada di payload / nilai akhirnya berubah
        $finalPrice = $data['price'] ?? $product->price;
        if (array_key_exists('discount_price', $data) && $data['discount_price'] !== null) {
            if ($data['discount_price'] >= $finalPrice) {
                return response()->json(['message' => 'discount_price must be less than price'], 422);
            }
        }

        $product->update($data);

        return response()->json(
            [
                'success' => true,
                'message' => 'Product updated successfully',
                'data'    => $product->fresh()->load(['bakery', 'discountEvent']),
            ],
            200
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::with('bakery:id,user_id')->findOrFail($id);

        $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $product->bakery_id);

        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    private function authorizeOwnerOrAdminByBakery(User $user, int $bakeryId): void
    {
        if ($user->role === 'admin') return;

        $owns = Bakery::where('id', $bakeryId)->where('user_id', $user->id)->exists();
        if (! $owns) {
            abort(403, 'Forbidden: you do not own this bakery.');
        }
    }
}
