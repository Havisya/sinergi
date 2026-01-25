<?php
// app/Http/Controllers/Api/LocationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsActivity; // <<< TAMBAHKAN

class LocationController extends Controller
{
    use LogsActivity; // <<< GUNAKAN TRAIT

    // GET /api/settings/locations (DIBIARKAN SAMA)
    public function index()
    {
        try {
             $locations = Location::all(['id', 'name', 'address']);
             return response()->json($locations);
        } catch (\Exception $e) {
            Log::error("Location Fetch Critical Error: " . $e->getMessage() . " di baris " . $e->getLine());
            return response()->json(['message' => 'Gagal memuat lokasi. Error kritis di server.'], 500);
        }
    }

    // POST /api/settings/locations
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        $location = Location::create($validated);

        // --- LOG AKTIVITAS ---
        $this->logActivity('CREATE', "Menambah lokasi baru: {$location->name}", 'Lokasi');
        // ---------------------

        return response()->json([
            'message' => 'Lokasi berhasil ditambahkan',
            'data' => $location
        ], 201);
    }

    // PUT/PATCH /api/settings/locations/{id}
    public function update(Request $request, $id)
    {
        try {
            $location = Location::findOrFail($id);
            $oldName = $location->name;

            $request->validate([
                 'name' => 'required|string|max:255',
                 'address' => 'nullable|string',
                 // Tambahkan rule unique jika kolom name harus unik
                 // 'name' => ['required', Rule::unique('locations')->ignore($id)],
            ]);

            $location->update($request->all());

            // --- LOG AKTIVITAS ---
            $this->logActivity('UPDATE', "Memperbarui lokasi: {$oldName} menjadi {$location->name}", 'Lokasi');
            // ---------------------

            return response()->json(['message' => 'Data lokasi berhasil diperbarui!']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Lokasi tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Location Update Error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memperbarui data.'], 500);
        }
    }

    // DELETE /api/settings/locations/{id}
    public function destroy($id)
    {
        try {
            $location = Location::findOrFail($id);
            $locationName = $location->name;
            $location->delete();

            // --- LOG AKTIVITAS ---
            $this->logActivity('DELETE', "Menghapus lokasi: {$locationName}", 'Lokasi');
            // ---------------------

            return response()->json(['message' => 'Lokasi berhasil dihapus!']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
             return response()->json(['message' => 'Lokasi tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Location Delete Error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus data.'], 500);
        }
    }
}
