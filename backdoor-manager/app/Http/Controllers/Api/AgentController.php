<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Alert;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'app_id'       => 'required|string', // tetap unique
            'app_name'     => 'required|string',
            'hostname'     => 'required|string',
            'ip_address'   => 'required|string',
            'public_key'   => 'required|string',
            'tech_stack'   => 'nullable|array',
        ]);

        // Cari agent dengan app_name + hostname yang sama (kunci utama untuk rotasi)
        $existingAgent = Agent::where('app_name', $validated['app_name'])
            ->where('hostname', $validated['hostname'])
            ->first();

        if ($existingAgent) {
            // Rotasi key: public_key berbeda
            if ($existingAgent->public_key !== $validated['public_key']) {
                $existingAgent->old_public_key = $existingAgent->public_key;
                $existingAgent->public_key = $validated['public_key'];
                $existingAgent->key_rotated_at = now();
                $existingAgent->status = 'pending'; // butuh approve ulang
                $existingAgent->ip_address = $validated['ip_address'];
                $existingAgent->tech_stack = $validated['tech_stack'] ?? $existingAgent->tech_stack;
                $existingAgent->save();

                return response()->json([
                    'message' => 'Rotasi key terdeteksi. Agent menunggu approval ulang.',
                    'status'  => 'key_rotated',
                ], 200);
            }

            // Key sama → hanya update info
            $existingAgent->ip_address = $validated['ip_address'];
            $existingAgent->tech_stack = $validated['tech_stack'] ?? $existingAgent->tech_stack;
            $existingAgent->last_seen_at = now();
            $existingAgent->save();

            return response()->json(['message' => 'Agent sudah terdaftar'], 200);
        }

        // Agent baru
        Agent::create([
            'app_id'         => $validated['app_id'],
            'app_name'       => $validated['app_name'],
            'hostname'       => $validated['hostname'],
            'ip_address'     => $validated['ip_address'],
            'public_key'     => $validated['public_key'],
            'tech_stack'     => $validated['tech_stack'] ?? null,
            'status'         => 'pending',
            'registered_at'  => now(),
        ]);

        return response()->json(['message' => 'Agent baru terdaftar. Menunggu approval.'], 201);
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'app_id' => 'required|string|exists:agents,app_id',
            'alerts' => 'required|array|min:1',
        ]);

        $agent = Agent::where('app_id', $validated['app_id'])->firstOrFail();

        if ($agent->status !== 'approved') {
            return response()->json(['error' => 'Agent belum di-approve atau telah di-revoke.'], 403);
        }

        foreach ($validated['alerts'] as $alertData) {
            Alert::create([
                'agent_id'      => $agent->id,
                'type'          => $alertData['type'],
                'file_path'     => $alertData['file'] ?? null,
                'hash'          => $alertData['hash'] ?? null,
                'matched_rules' => $alertData['matched_rules'] ?? null,
                'raw_data'      => $alertData,
                'detected_at'   => now(),
            ]);
        }

        $agent->update(['last_seen_at' => now()]);

        return response()->json(['message' => 'Alert diterima', 'count' => count($validated['alerts'])], 200);
    }
}