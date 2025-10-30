<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Models\OperatingHour;
use App\Models\Bakery;
use App\Models\User;
use Illuminate\Validation\Rule;

class ApiOperatingHourController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id)
    {
        $bakery = Bakery::find($id);
        if (!$bakery) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }
        $hours = OperatingHour::where('bakery_id', $bakery->id)
            ->orderBy('day_of_the_week')
            ->get();

        return response()->json([
            'bakery' => $bakery->only(['id', 'name']),
            'hours'  => $hours,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (!$bakery) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }
        $this->authorizeOwnerOrAdminByBakery($request->user(), $bakery->id);

        $data = $request->validate([
            'day_of_the_week' => [
                'required',
                'integer',
                'between:1,7',
                Rule::unique('operating_hours', 'day_of_the_week')->where('bakery_id', $bakery->id),
            ],
            'is_closed'  => ['required', 'boolean'],
            'open_time'  => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
        ]);

        // business rule sederhana seperti di Bakery
        if ($data['is_closed'] === false) {
            $request->validate([
                'open_time'  => ['required', 'date_format:H:i'],
                'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            ]);
        } else {
            $data['open_time']  = null;
            $data['close_time'] = null;
        }

        $data['bakery_id'] = $bakery->id;

        $row = OperatingHour::create($data);

        return response()->json($row, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $row = OperatingHour::with('bakery:id,name,user_id')->findOrFail($id);
            return response()->json($row);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Operating hour not found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $row = OperatingHour::with('bakery:id,user_id')->find($id);
        if (! $row) {
            return response()->json(['message' => 'Operating hour not found'], 404);
        }
        $this->authorizeOwnerOrAdminByBakery($request->user(), $row->bakery_id);

        $data = $request->validate([
            'day_of_the_week' => [
                'sometimes',
                'integer',
                'between:1,7',
                Rule::unique('operating_hours', 'day_of_the_week')
                    ->where('bakery_id', $row->bakery_id)
                    ->ignore($row->id),
            ],
            'is_closed'  => ['sometimes', 'boolean'],
            'open_time'  => ['sometimes', 'nullable', 'date_format:H:i'],
            'close_time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ]);

        // gabungkan nilai final untuk validasi jam
        $isClosed = array_key_exists('is_closed', $data) ? (bool)$data['is_closed'] : (bool)$row->is_closed;

        if ($isClosed === false) {
            $open  = $data['open_time']  ?? $row->open_time;
            $close = $data['close_time'] ?? $row->close_time;

            if (!$open || !$close) {
                return response()->json(['message' => 'open_time and close_time are required when not closed'], 422);
            }
            if (strtotime($close) <= strtotime($open)) {
                return response()->json(['message' => 'close_time must be after open_time'], 422);
            }
        } else {
            $data['open_time']  = null;
            $data['close_time'] = null;
        }

        $row->update($data);

        return response()->json([
            'message' => 'Operating hour updated',
            'operating_hour' => $row->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $row = OperatingHour::with('bakery:id,user_id')->find($id);
        if (! $row) {
            return response()->json(['message' => 'Operating hour not found'], 404);
        }
        $this->authorizeOwnerOrAdminByBakery($request->user(), $row->bakery_id);

        $row->delete();

        return response()->json(['message' => 'Operating hour deleted']);
    }

    // helper to authorize owner or admin by bakery
    private function authorizeOwnerOrAdminByBakery(User $user, int $bakeryId): void
    {
        if ($user->role === 'admin') return;

        $owns = Bakery::where('id', $bakeryId)->where('user_id', $user->id)->exists();
        if (! $owns) {
            abort(403, 'Forbidden');
        }
    }
}
