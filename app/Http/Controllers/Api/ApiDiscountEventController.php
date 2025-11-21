<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExpireProductDiscount;
use App\Models\DiscountEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ApiDiscountEventController extends Controller
{
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'discount_name'       => ['required', 'string', 'max:150'],
            'discount'            => ['required', 'integer', 'between:1,100'],
            'discount_photo'      => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'discount_start_time' => ['required', 'date'],
            'discount_end_time'   => ['required', 'date', 'after:discount_start_time'],
            'bakery_id'           => ['required', 'exists:bakeries,id']
        ]);

        $data['discount_start_time'] = Carbon::parse($data['discount_start_time']);
        $data['discount_end_time']   = Carbon::parse($data['discount_end_time']);

        // Upload foto (opsional) → simpan URL absolut
        if ($request->hasFile('discount_photo')) {
            $path = $request->file('discount_photo')->store('discount_photos', 'public');
            $data['discount_photo'] = url(Storage::url($path));
        } else {
            $data['discount_photo'] = null;
        }

        $row = DiscountEvent::create($data);

        ExpireProductDiscount::dispatch($row->id)->delay($row->discount_end_time);

        return response()->json([
            'success' => true,
            'message' => 'Discount event created',
            'data'    => $row,
        ], 201);
    }

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

    public function getEventsByBakeryId(string $bakeryId)
    {
        $events = DiscountEvent::with('products')->where('bakery_id', $bakeryId)->get();
        if (!$events) {
            return response()->json(['message' => 'Discount Events Not Found']);
        }
        return response()->json([
            'message' => 'Discount Events Sucessfully Retrieved',
            'data'  =>  $events
        ]);
    }

    public function update(Request $request, $id)
    {
        $row = DiscountEvent::find($id);
        if (! $row) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        $data = $request->validate([
            'discount_name'       => ['sometimes', 'string', 'max:150'],
            'discount'            => ['sometimes', 'integer', 'between:1,100'],
            // ganti: URL -> FILE (multipart) seperti produk
            'discount_photo'      => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'discount_start_time' => ['sometimes', 'date'],
            'discount_end_time'   => ['sometimes', 'date'],
        ]);

        // simpan nilai lama sebelum update
        $oldDiscount = (int) $row->discount;

        // Validasi kombinasi waktu (pakai nilai baru kalau ada)
        $start = array_key_exists('discount_start_time', $data)
            ? Carbon::parse($data['discount_start_time'])
            : Carbon::parse($row->discount_start_time);

        $end = array_key_exists('discount_end_time', $data)
            ? Carbon::parse($data['discount_end_time'])
            : Carbon::parse($row->discount_end_time);

        if ($end->lessThanOrEqualTo($start)) {
            return response()->json(['message' => 'discount_end_time must be after discount_start_time'], 422);
        }

        // Jika ada file foto baru → hapus lama & simpan baru
        if ($request->hasFile('discount_photo')) {
            if (!empty($row->discount_photo)) {
                $oldPath = ltrim(str_replace('/storage/', '', parse_url($row->discount_photo, PHP_URL_PATH) ?? ''), '/');
                if ($oldPath !== '') {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $path = $request->file('discount_photo')->store('discount_photos', 'public');
            $data['discount_photo'] = url(Storage::url($path));
        }
        // kalau tidak ada file yang dikirim, biarkan foto lama

        // Simpan kembali
        $row->update([
            'discount_name'       => $data['discount_name'] ?? $row->discount_name,
            'discount'            => array_key_exists('discount', $data) ? (int) $data['discount'] : $row->discount,
            'discount_photo'      => array_key_exists('discount_photo', $data) ? $data['discount_photo'] : $row->discount_photo,
            'discount_start_time' => $start,
            'discount_end_time'   => $end,
        ]);

        // kalau discount% berubah → update semua produk yang pakai event ini
        if (array_key_exists('discount', $data)) {
            $newDiscount = (int) $row->discount;

            if ($newDiscount !== $oldDiscount) {
                foreach ($row->products as $product) {
                    $product->applyDiscount($row);
                }
            }
        }
        ExpireProductDiscount::dispatch($row->id)->delay($row->discount_end_time);

        return response()->json([
            'success' => true,
            'message' => 'Discount event updated',
            'data'    => $row->fresh()->loadCount('products'),
        ]);
    }

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
            'success' => true,
            'message' => 'Products attached to discount event',
            'count'   => $products->count(),
        ]);
    }

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
            'success' => true,
            'message' => 'Products detached from discount event',
            'count'   => $products->count(),
        ]);
    }

    public function destroy($id)
    {
        $row = DiscountEvent::find($id);
        if (! $row) {
            return response()->json(['message' => 'Discount event not found'], 404);
        }

        // Lepas relasi produk
        Product::where('discount_id', $row->id)->update(['discount_id' => null]);

        // Hapus file foto jika ada (sesuai pola produk)
        if (!empty($row->discount_photo)) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url($row->discount_photo, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '') {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Discount event deleted'
        ], 200);
    }
}
