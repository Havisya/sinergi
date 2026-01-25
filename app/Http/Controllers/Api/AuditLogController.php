<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // Gunakan Log facade untuk mencatat error

class AuditLogController extends Controller
{
    /**
     * Tampilkan daftar audit logs dengan fitur search, filter, dan paginasi.
     * GET /api/audit-logs?page=...&search=...&category=...&per_page=...
     */
    public function index(Request $request)
    {
        try {
            // 1. Ambil input dari request
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');
            $category = $request->get('category');

            // Terapkan batas maksimum per_page untuk keamanan
            $perPage = min(50, (int)$perPage);

            // 2. Query dasar ke tabel log (Menggunakan 'activity_logs')
            // Menggunakan query builder untuk fleksibilitas
            $query = DB::table('activity_logs')
                        ->orderByDesc('created_at');

            // 3. Terapkan filter pencarian
            if ($search) {
                // Pencarian berdasarkan user_name, action, atau details
                $query->where(function ($q) use ($search) {
                    $q->where('user_name', 'like', '%' . $search . '%')
                      ->orWhere('action', 'like', '%' . $search . '%')
                      ->orWhere('details', 'like', '%' . $search . '%');
                });
            }

            // 4. Terapkan filter kategori (kecuali 'Semua', jika ini digunakan)
            if ($category && $category !== 'Semua') {
                $query->where('category', $category);
            }

            // 5. Paginate hasil
            $logs = $query->paginate($perPage);

            // Mapping data untuk format respons API
            $data = $logs->getCollection()->map(function ($log) {
                // Konversi waktu ke zona waktu Indonesia (Asia/Jakarta)
                $createdAt = Carbon::parse($log->created_at)->setTimezone('Asia/Jakarta')->toDateTimeString();

                // user_name akan berisi nama user atau 'Sistem/Unknown'
                $userName = $log->user_name ?? 'Sistem/Unknown';

                return [
                    'id' => $log->id,
                    'user' => $userName, // Ini adalah kunci yang akan menampilkan nama di frontend
                    'action' => $log->action,
                    'details' => $log->details,
                    'category' => $log->category ?? 'Lain-lain', // Default kategori jika null
                    'timestamp' => $createdAt,
                ];
            });

            // 6. Kembalikan respons yang diformat
            return response()->json([
                'status' => 'success',
                'message' => 'Audit logs berhasil dimuat.',
                'data' => [
                    'current_page' => $logs->currentPage(),
                    'data' => $data,
                    'per_page' => $logs->perPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            // Catat error ke log server Laravel
            Log::error("Error memuat audit logs: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server saat memuat log.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengambil daftar kategori unik dari audit logs.
     * GET /api/audit-logs/categories
     */
    public function getCategories()
    {
        try {
            // Ambil semua kategori unik, hilangkan yang kosong (null), dan konversi ke array
            $categories = DB::table('activity_logs')
                            ->distinct()
                            ->pluck('category')
                            ->filter() // Menghapus kategori null/kosong
                            ->values()
                            ->toArray();

            // Tambahkan 'Semua' di awal array kategori
            array_unshift($categories, 'Semua');

            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil dimuat.',
                'categories' => array_unique($categories), // Pastikan tidak ada duplikasi
            ]);

        } catch (\Exception $e) {
            Log::error("Error memuat kategori log: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat kategori.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
