<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use Illuminate\Http\Request;

class ApiBakeryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Bakery::query()
            ->with('user:id,name,email')
            ->when($request->boolean('only_active'), fn($q) => $q->where('is_active', true))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', (int) $request->user_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim($request->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                        ->orWhere('address', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('id');

        $bakeries = $query->paginate($request->integer('per_page', 10));
        return response()->json($bakeries);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
