<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // return response()->json($products, 200);
        return response()->json([
            'success' => true,
            'message' => 'Product list retrieved successfully',
            'data'    => $products
        ], 200);
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
            'image_url'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'discount_price' => ['nullable', 'integer', 'min:0'],
            'discount_id'    => ['nullable', 'integer', 'exists:discount_events,id'],
        ]);

        // pastikan user adalah owner bakery atau admin
        $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $data['bakery_id']);

        // aturan kecil: kalau ada discount_price, harus < price
        if (isset($data['discount_price']) && $data['discount_price'] >= $data['price']) {
            return response()->json(['message' => 'discount_price must be less than price'], 422);
        }

        // Upload foto (jika ada)
        $imageUrl = null;
        if ($request->hasFile('image_url')) {
            $path = $request->file('image_url')->store('product_photos', 'public');
            $imageUrl = Storage::url($path);
            $imageUrl = url($imageUrl);
        }

        $product = Product::create([
            'bakery_id'      => $data['bakery_id'],
            'product_name'   => $data['product_name'],
            'description'    => $data['description'] ?? null,
            'price'          => $data['price'],
            'best_before'    => $data['best_before'] ?? null,
            'image_url'      => $imageUrl,
            'discount_price' => $data['discount_price'] ?? null,
            'discount_id'    => $data['discount_id'] ?? null,
        ])->load(['bakery', 'discountEvent']);

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

        // Pastikan user berhak mengelola produk ini
        $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $product->bakery_id);

        $data = $request->validate([
            'product_name'   => ['sometimes', 'string', 'max:150'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'price'          => ['sometimes', 'integer', 'min:0'],
            'best_before'    => ['sometimes', 'nullable', 'integer', Rule::in([1, 2, 3])],
            'image_url'      => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status'         => ['sometimes', 'in:available,not_available'],
        ]);

        // Jika ada file image baru, hapus lama & simpan yang baru
        if ($request->hasFile('image_url')) {
            // Hapus file lama bila ada
            if (!empty($product->image_url)) {
                $oldPath = ltrim(str_replace('/storage/', '', parse_url($product->image_url, PHP_URL_PATH) ?? ''), '/');
                if ($oldPath !== '') {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            // Simpan file baru (nama unik otomatis)
            $path = $request->file('image_url')->store('product_photos', 'public');
            $data['image_url'] = url(Storage::url($path));
        }

        // Update field lain (yang dikirim saja yang diubah)
        $product->update([
            'product_name'   => $data['product_name'] ?? $product->product_name,
            'description'    => array_key_exists('description', $data)    ? $data['description']    : $product->description,
            'price'          => $data['price'] ?? $product->price,
            'best_before'    => array_key_exists('best_before', $data)    ? $data['best_before']    : $product->best_before,
            'image_url'      => array_key_exists('image_url', $data)      ? $data['image_url']      : $product->image_url,
            'discount_id'    => array_key_exists('discount_id', $data)    ? $data['discount_id']    : $product->discount_id,
            'status'         => $data['status'] ?? $product->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data'    => $product->fresh()->load(['bakery', 'discountEvent']),
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::with('bakery:id,user_id')->findOrFail($id);

        // pastikan user berhak
        $this->authorizeOwnerOrAdminByBakery($request->user(), (int) $product->bakery_id);

        // hapus foto fisik jika ada
        $this->deleteImageIfExists($product->image_url);

        // hapus product dari database
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ], 200);
    }

    // Hapus image dari storage jika ada
    private function deleteImageIfExists(?string $imageUrl): void
    {
        if (!$imageUrl) return;

        // Ambil path dari URL, buang prefix `/storage/`
        $path = parse_url($imageUrl, PHP_URL_PATH) ?: '';
        $relative = ltrim(str_replace('/storage/', '', $path), '/');

        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
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
