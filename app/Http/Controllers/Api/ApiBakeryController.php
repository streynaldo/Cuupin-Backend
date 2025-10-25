<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\User;
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
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string'],
            'logo_url'     => ['nullable', 'url'],
            'banner_url'   => ['nullable', 'url'],
            'address'      => ['nullable', 'string', 'max:255'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'contact_info' => ['nullable', 'string', 'max:100'],
            'is_active'    => ['boolean'],
        ]);

        $data['user_id'] = $request->user()->id;

        $bakery = Bakery::create($data)->load('user:id,name,email');

        return response()->json($bakery, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $bakery = Bakery::findOrFail($id);
        $bakery->load('user:id,name,email');
        return response()->json($bakery);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $bakery = Bakery::findOrFail($id);
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:150'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'logo_url'     => ['sometimes', 'nullable', 'url'],
            'banner_url'   => ['sometimes', 'nullable', 'url'],
            'address'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude'     => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_info' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $bakery->update($data);

        return response()->json($bakery->fresh()->load('user:id,name,email'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $bakery = Bakery::findOrFail($id);
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $bakery->delete();

        return response()->json(['message' => 'Bakery deleted']);
    }

    // Helper to authorize owner or admin
    private function authorizeOwnerOrAdmin(User $user, Bakery $bakery): void
    {
        if (!($user->role === 'admin' || $bakery->user_id === $user->id)) {
            abort(403, 'Forbidden');
        }
    }
}
