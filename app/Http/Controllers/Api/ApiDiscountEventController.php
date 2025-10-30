<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ApiDiscountEventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rows = DiscountEvent::withCount('products')
            ->orderByDesc('discount_start_time')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Discount events retrieved successfully',
            'data'    => $rows
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'discount_name'       => ['required', 'string', 'max:150'],
            'discount'            => ['required', 'integer', 'between:1,100'],
            'discount_photo'      => ['nullable', 'url'],
            'discount_start_time' => ['required', 'date'],
            'discount_end_time'   => ['required', 'date', 'after:discount_start_time'],
        ]);

        $data['discount_start_time'] = Carbon::parse($data['discount_start_time']);
        $data['discount_end_time']   = Carbon::parse($data['discount_end_time']);

        $row = DiscountEvent::create($data);

        return response()->json([
            'message' => 'Discount event created',
            'data'    => $row,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $row = DiscountEvent::with([
            'products:id,product_name,bakery_id,discount_id'
        ])->find($id);

        if (! $row) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        return response()->json($row);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $row = DiscountEvent::find($id);
        if (! $row) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        $data = $request->validate([
            'discount_name'       => ['sometimes', 'string', 'max:150'],
            'discount'            => ['sometimes', 'integer', 'between:1,100'],
            'discount_photo'      => ['sometimes', 'nullable', 'url'],
            'discount_start_time' => ['sometimes', 'date'],
            'discount_end_time'   => ['sometimes', 'date'],
        ]);

        // Validasi after:open saat kombinasi waktu berubah
        $start = array_key_exists('discount_start_time', $data)
            ? Carbon::parse($data['discount_start_time'])
            : Carbon::parse($row->discount_start_time);

        $end = array_key_exists('discount_end_time', $data)
            ? Carbon::parse($data['discount_end_time'])
            : Carbon::parse($row->discount_end_time);

        if ($end->lessThanOrEqualTo($start)) {
            return response()->json(['message' => 'discount_end_time must be after discount_start_time'], 422);
        }

        // Simpan kembali (pastikan Carbon ke string jika perlu)
        $data['discount_start_time'] = $start;
        $data['discount_end_time']   = $end;

        $row->update($data);

        return response()->json([
            'message' => 'Discount event updated',
            'data'    => $row->fresh()->loadCount('products'),
        ]);
    }

    /**
     * POST /discount-events/{id}/products
     * Assign multiple products to a discount event
     */
    public function attachProducts(Request $request, $id)
    {
        $event = DiscountEvent::find($id);
        if (! $event) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        $data = $request->validate([
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $products = Product::whereIn('id', $data['product_ids'])->get();

        foreach ($products as $product) {
            $product->applyDiscount($event);
        }

        return response()->json([
            'message' => 'Products attached to discount event',
            'count'   => $products->count(),
        ]);
    }

    /**
     * DELETE /discount-events/{id}/products
     * Remove products from a discount event
     */
    public function detachProducts(Request $request, $id)
    {
        $event = DiscountEvent::find($id);
        if (! $event) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        $data = $request->validate([
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $products = Product::where('discount_id', $event->id)
            ->whereIn('id', $data['product_ids'])
            ->get();

        foreach ($products as $product) {
            $product->clearDiscount();
        }

        return response()->json([
            'message' => 'Products detached from discount event',
            'count'   => $products->count(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $row = DiscountEvent::find($id);
        if (! $row) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        Product::where('discount_id', $row->id)->update(['discount_id' => null]);
        $row->delete();

        return response()->json(['message' => 'Discount event deleted']);
    }
}
