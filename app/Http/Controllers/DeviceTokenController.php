<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string',
            'device_name' => 'nullable|string',
        ]);

        try {
            $deviceToken = DeviceToken::updateOrCreate(
                ['token' => $data['token']],
                [
                    'user_id' => $request->user()->id,
                    'platform' => $data['platform'] ?? 'ios',
                    'device_name' => $data['device_name'] ?? null,
                    'last_seen_at' => Carbon::now(),
                ]
            );

            return response()->json(['ok' => true, 'data' => $deviceToken], 200);
        } catch (\Throwable $e) {
            Log::warning('Failed to store device token: ' . $e->getMessage(), ['payload' => $data]);
            return response()->json(['ok' => false, 'message' => 'Failed to register device token'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(DeviceToken $deviceToken)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeviceToken $deviceToken)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeviceToken $deviceToken)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $deleted = DeviceToken::where('token', $data['token'])
                ->where('user_id', $request->user()->id)
                ->delete();

            return response()->json(['ok' => true, 'deleted' => (bool) $deleted], 200);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete device token: ' . $e->getMessage(), ['token' => $data['token']]);
            return response()->json(['ok' => false, 'message' => 'Failed to delete device token'], 500);
        }
    }
}
