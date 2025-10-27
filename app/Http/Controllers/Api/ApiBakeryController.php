<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApiBakeryController extends Controller
{
    /**
     * GET /bakeries
     */
    public function index(Request $request)
    {
        $request->validate([
            'only_active' => ['sometimes', 'boolean'],
            'user_id'     => ['sometimes', 'integer', 'exists:users,id'],
            'search'      => ['sometimes', 'string', 'max:100'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

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
     * POST /bakeries  (butuh login + ability)
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

        try {
            $bakery = Bakery::create($data)->load('user:id,name,email');
            return response()->json($bakery, 201);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Failed to create bakery'], 500);
        }
    }

    /**
     * GET /bakeries/{id}
     */
    public function show(int $id)
    {
        try {
            $bakery = Bakery::with('user:id,name,email')->findOrFail($id);
            return response()->json($bakery);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }
    }

    /**
     * PUT /bakeries/{id}  (butuh login + ability)
     */
    public function update(Request $request, int $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }
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

        // fresh() to get updated relations
        return response()->json([
            $bakery->fresh()->load('user:id,name,email'),
            'message' => 'Bakery updated'
        ], 200);
    }

    /**
     * DELETE /bakeries/{id}  (butuh login + ability)
     */
    public function destroy(Request $request, int $id)
    {
        $bakery = Bakery::findOrFail($id); // auto 404
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $bakery->delete();

        // 200 dengan message, atau 204 tanpa body (pilih salah satu)
        return response()->json(['message' => 'Bakery deleted']);
        // return response()->noContent(); // <- alternatif 204
    }

    /** Helper: hanya admin atau owner bakery yang boleh write */
    private function authorizeOwnerOrAdmin(User $user, Bakery $bakery): void
    {
        if (! ($user->role === 'admin' || $bakery->user_id === $user->id)) {
            abort(403, 'Forbidden');
        }
    }
}
