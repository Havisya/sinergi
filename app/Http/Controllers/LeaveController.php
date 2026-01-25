<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\LeaveQuota;
use App\Models\PermitQuota;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Traits\LogsActivity; // <<< TAMBAHKAN

class LeaveController extends Controller
{
    // =================================================================
    // FUNGSI PEMBANTU
    // =================================================================

    /**
     * Fungsi pembantu untuk menghitung jumlah hari kerja antara dua tanggal.
     */
    protected function countBusinessDays($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = 0;

        while ($start->lte($end)) {
            if (!$start->isWeekend()) {
                $days++;
            }
            $start->addDay();
        }

        return $days;
    }

    // =================================================================
    // LOGIKA UNTUK CUTI TAHUNAN (leave_quotas)
    // =================================================================

    /**
     * Mengambil daftar karyawan beserta jatah cuti mereka.
     */
    public function indexQuotas()
    {
        try {
            $employeesWithQuotas = Karyawan::select(
                'karyawan.id',
                'karyawan.nopek',
                'karyawan.name',
                'karyawan.position',
            )
            ->leftJoin('leave_quotas', 'karyawan.nopek', '=', 'leave_quotas.nopek')
            ->selectRaw('IFNULL(leave_quotas.annual_quota, 12) as annual_quota')
            ->selectRaw('IFNULL(leave_quotas.leave_taken, 0) as leave_taken')
            ->selectRaw('IFNULL(leave_quotas.sick_leave_taken, 0) as sick_leave_taken')
            ->orderBy('karyawan.name')
            ->get();

            return response()->json($employeesWithQuotas);

        } catch (QueryException $e) {
            Log::error("Query Error indexQuotas: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal memuat kuota cuti. Ada masalah di kueri SQL.',
                'error_detail' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error("Fatal Error indexQuotas: " . $e->getMessage());
            return response()->json(['message' => 'Error Internal Server yang tidak terduga.', 'error_detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Menyimpan atau Memperbarui Kuota Cuti.
     */
    public function storeOrUpdateQuota(Request $request)
    {
        try {
            $validated = $request->validate([
                'nopek' => 'required|string|exists:karyawan,nopek',
                'annual_quota' => 'required|integer|min:0',
                'leave_taken' => 'required|integer|min:0',
                'sick_leave_taken' => 'required|integer|min:0',
            ]);

            $quota = LeaveQuota::updateOrCreate(
                ['nopek' => $validated['nopek']],
                [
                    'annual_quota' => $validated['annual_quota'],
                    'leave_taken' => $validated['leave_taken'],
                    'sick_leave_taken' => $validated['sick_leave_taken'],
                ]
            );
            // --- LOG AKTIVITAS ---
            $action = $isNew ? 'CREATE' : 'UPDATE';
            $details = "{$action} kuota cuti tahunan untuk {$employee} (Jatah: {$quota->annual_quota}, Terambil: {$quota->leave_taken}).";
            $this->logActivity($action, $details, 'Pengaturan');


            return response()->json(['message' => 'Kuota cuti berhasil diperbarui', 'data' => $quota], 200);
        } catch (\Exception $e) {
            Log::error("Error saving quota: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan kuota cuti.', 'error_detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Menghapus Kuota Cuti.
     */
    public function destroyQuota($nopek)
    {
        try {
            $deleted = LeaveQuota::where('nopek', $nopek)->delete();

            if ($deleted) {
                return response()->json(['message' => 'Kuota cuti berhasil dihapus.'], 200);
            }
            // --- LOG AKTIVITAS ---
            $this->logActivity('DELETE', "Menghapus Kuota Cuti Tahunan untuk {$employee} (NIP: {$nopek}).", 'Pengaturan');


            return response()->json(['message' => 'Kuota cuti tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Error deleting quota: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus kuota cuti. Error Internal.'], 500);
        }
    }


    // =================================================================
    // LOGIKA UNTUK IZIN (permit_quotas)
    // =================================================================

    /**
     * Mengambil daftar karyawan beserta jatah izin mereka.
     */
    public function indexPermits()
    {
        try {
            $employeesWithPermits = Karyawan::select(
                'karyawan.id',
                'karyawan.nopek',
                'karyawan.name',
                'karyawan.position',
            )
            ->leftJoin('permit_quotas', 'karyawan.nopek', '=', 'permit_quotas.nopek')
            ->selectRaw('IFNULL(permit_quotas.permit_quota, 5) as permit_quota')
            ->selectRaw('IFNULL(permit_quotas.permit_taken, 0) as permit_taken')
            ->orderBy('karyawan.name')
            ->get();

            return response()->json($employeesWithPermits);

        } catch (QueryException $e) {
            Log::error("Query Error indexPermits: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal memuat kuota izin. Ada masalah di kueri SQL.',
                'error_detail' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error("Fatal Error indexPermits: " . $e->getMessage());
            return response()->json(['message' => 'Error Internal Server yang tidak terduga.', 'error_detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Menyimpan atau Memperbarui Kuota Izin.
     */
    public function storeOrUpdatePermit(Request $request)
    {
        try {
            $validated = $request->validate([
                'nopek' => 'required|string|exists:karyawan,nopek',
                'permit_quota' => 'required|integer|min:0',
                'permit_taken' => 'required|integer|min:0',
            ]);

            $quota = PermitQuota::updateOrCreate(
                ['nopek' => $validated['nopek']],
                [
                    'permit_quota' => $validated['permit_quota'],
                    'permit_taken' => $validated['permit_taken'],
                ]
            );
            // --- LOG AKTIVITAS ---
            $action = $isNew ? 'CREATE' : 'UPDATE';
            $details = "{$action} kuota izin untuk {$employee} (Jatah: {$quota->permit_quota}, Terambil: {$quota->permit_taken}).";
            $this->logActivity($action, $details, 'Pengaturan');


            return response()->json(['message' => 'Kuota izin berhasil diperbarui', 'data' => $quota], 200);
        } catch (\Exception $e) {
            Log::error("Error saving permit quota: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan kuota izin.', 'error_detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Menghapus Kuota Izin.
     */
    public function destroyPermit($nopek)
    {
        try {
            $deleted = PermitQuota::where('nopek', $nopek)->delete();

            if ($deleted) {
                return response()->json(['message' => 'Kuota izin berhasil dihapus.'], 200);
            }
            // --- LOG AKTIVITAS ---
            $this->logActivity('DELETE', "Menghapus Kuota Izin untuk {$employee} (NIP: {$nopek}).", 'Pengaturan');


            return response()->json(['message' => 'Kuota izin tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            Log::error("Error deleting permit quota: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus kuota izin. Error Internal.'], 500);
        }
    }
}
