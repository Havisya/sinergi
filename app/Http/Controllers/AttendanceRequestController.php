<?php namespace App\Http\Controllers;

use App\Models\AttendanceRequest;
use App\Models\Karyawan;
use App\Models\LeaveQuota;
use App\Models\PermitQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsActivity;


class AttendanceRequestController extends Controller
{
    // Pastikan Trait LogsActivity digunakan agar metode logActivity tersedia
    use LogsActivity; // <<< PERBAIKAN: Tambahkan penggunaan Trait

    /**
     * Helper: Menghitung hari kerja dalam rentang (mengabaikan akhir pekan Sabtu/Minggu).
     */
    private function getWorkingDays(string $dateFrom, string $dateTo): int
    {
        try {
            $start = Carbon::parse($dateFrom);
            $end = Carbon::parse($dateTo);
            if ($start->greaterThan($end)) return 0;

            $period = CarbonPeriod::create($start, $end);
            $workingDays = 0;

            foreach ($period as $date) {
                // Asumsi: Sabtu dan Minggu adalah hari libur (tidak dihitung hari kerja)
                if ($date->dayOfWeek !== Carbon::SATURDAY && $date->dayOfWeek !== Carbon::SUNDAY) {
                    $workingDays++;
                }
            }
            return $workingDays;
        } catch (\Exception $e) {
            Log::error("Error calculating working days: " . $e->getMessage());
            return 0;
        }
    }

    // =================================================================
    // LOGIKA CRUD & INDEX
    // =================================================================

    public function index()
    {
        try {
            $requests = AttendanceRequest::where('status', 'Pending')
                                         ->orderBy('date_from', 'asc')
                                         ->get();

            $formattedRequests = $requests->map(function ($item) {
                return [
                    'id' => $item->id,
                    'employee' => $item->employee_name,
                    'nopek' => $item->nopek,
                    // PERBAIKAN: Gunakan null-safe operator untuk date_from/date_to
                    'date_from' => $item->date_from ? $item->date_from->format('Y-m-d') : null,
                    'date_to' => $item->date_to ? $item->date_to->format('Y-m-d') : null,
                    'type' => $item->type,
                    'reason' => $item->reason,
                    'details' => $item->details,
                    'status' => $item->status,
                ];
            });

            return response()->json($formattedRequests);

        } catch (\Exception $e) {
            Log::error("Error fetching PENDING attendance requests: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat daftar pengajuan presensi.'], 500);
        }
    }

    public function indexHistory()
    {
        try {
            $requests = AttendanceRequest::orderBy('date_from', 'desc')
                                         ->get();

            $formattedRequests = $requests->map(function ($item) {
                return [
                    'id' => $item->id,
                    'employee' => $item->employee_name,
                    'nopek' => $item->nopek,
                    // PERBAIKAN: Gunakan null-safe operator untuk date_from/date_to
                    'date_from' => $item->date_from ? $item->date_from->format('Y-m-d') : null,
                    'date_to' => $item->date_to ? $item->date_to->format('Y-m-d') : null,
                    'type' => $item->type,
                    'reason' => $item->reason,
                    'details' => $item->details,
                    'status' => $item->status,
                ];
            });

            return response()->json($formattedRequests);

        } catch (\Exception $e) {
            Log::error("Error fetching ALL attendance history: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat riwayat pengajuan.'], 500);
        }
    }


    /**
     * Menyimpan pengajuan baru.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nopek' => 'required|string|max:255',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'type' => 'required|in:Izin Cuti,Izin Sakit,Izin Khusus,Cuti Tahunan',
                'reason' => 'required|string|max:255',
                'details' => 'nullable|string',
            ]);

            $employee = Karyawan::where('nopek', $validatedData['nopek'])->first();
            if (!$employee) {
                return response()->json(['message' => 'Nopek tidak valid atau karyawan tidak ditemukan.'], 404);
            }

            $request = AttendanceRequest::create([
                'nopek' => $validatedData['nopek'],
                'employee_name' => $employee->name,
                'date_from' => $validatedData['date_from'],
                'date_to' => $validatedData['date_to'],
                'type' => $validatedData['type'],
                'reason' => $validatedData['reason'],
                'details' => $validatedData['details'] ?? '',
                'status' => 'Pending',
                'quota_deducted' => false,
            ]);

            // --- LOG AKTIVITAS ---
            $this->logActivity(
                'CREATE',
                "Mengajukan {$request->type} untuk {$employee->name} ({$validatedData['date_from']} s/d {$validatedData['date_to']}).",
                'Presensi'
            );


            return response()->json($request, 201);

        } catch (ValidationException $e) {
            Log::warning("Validation failed on store: " . $e->getMessage());
            return response()->json(['errors' => $e->errors(), 'message' => 'Tipe pengajuan tidak valid atau data kurang lengkap.'], 422);
        }
    }

    /**
     * Memperbarui pengajuan yang spesifik.
     */
    public function update(Request $request, $id)
    {
        $attendanceRequest = AttendanceRequest::findOrFail($id);

        if ($attendanceRequest->status !== 'Pending') {
            return response()->json(['message' => 'Pengajuan yang sudah diproses tidak dapat diubah.'], 403);
        }

        try {
            $validatedData = $request->validate([
                'nopek' => 'required|string|max:255',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'type' => 'required|in:Izin Cuti,Izin Sakit,Izin Khusus,Cuti Tahunan',
                'reason' => 'required|string|max:255',
                'details' => 'nullable|string',
            ]);

            $employee = Karyawan::where('nopek', $validatedData['nopek'])->first();
            if (!$employee) {
                return response()->json(['message' => 'Nopek tidak valid atau karyawan tidak ditemukan.'], 404);
            }

            $attendanceRequest->update([
                'nopek' => $validatedData['nopek'],
                'employee_name' => $employee->name,
                'date_from' => $validatedData['date_from'],
                'date_to' => $validatedData['date_to'],
                'type' => $validatedData['type'],
                'reason' => $validatedData['reason'],
                'details' => $validatedData['details'] ?? '',
                'status' => 'Pending',
                'quota_deducted' => false,
            ]);

            $this->logActivity(
                'UPDATE',
                "Mengubah pengajuan {$attendanceRequest->type} untuk {$attendanceRequest->employee_name} (ID: {$id}).",
                'Presensi'
            );

            return response()->json($attendanceRequest, 200);

        } catch (ValidationException $e) {
            Log::warning("Validation failed on update: " . $e->getMessage());
            return response()->json(['errors' => $e->errors(), 'message' => 'Tipe pengajuan tidak valid atau data kurang lengkap.'], 422);
        }
    }

    /**
     * Menghapus pengajuan.
     */
    public function destroy($id)
    {
        $attendanceRequest = AttendanceRequest::findOrFail($id);

        if ($attendanceRequest->status === 'Approved' && $attendanceRequest->quota_deducted) {
             Log::warning("Pengajuan disetujui dihapus. Kuota perlu dikembalikan secara manual.");
        }

        // PERBAIKAN: Definisikan $details sebelum pemanggilan logActivity
        $details = "Menghapus pengajuan {$attendanceRequest->type} dari {$attendanceRequest->employee_name} (ID: {$id}).";

        $attendanceRequest->delete();

        // --- LOG AKTIVITAS ---
        $this->logActivity('DELETE', $details, 'Presensi'); // $details sekarang terdefinisi

        return response()->json(null, 204);
    }

    // =================================================================
    // LOGIKA APPROVAL & PEMOTONGAN KUOTA (KRITIS)
    // =================================================================

    public function approve(Request $request, $id)
    {
        return $this->processRequest($request, $id, 'Approved');
    }

    public function reject(Request $request, $id)
    {
        return $this->processRequest($request, $id, 'Rejected');
    }

    private function processRequest(Request $request, $id, $status)
    {
        $attendanceRequest = AttendanceRequest::findOrFail($id);

        if ($attendanceRequest->status !== 'Pending') {
            return response()->json(['message' => "Permintaan sudah berstatus '{$attendanceRequest->status}'."], 409);
        }

        $request->validate([
            'reviewer_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $attendanceRequest->update([
                'status' => $status,
                'reviewer_notes' => $request->input('reviewer_notes'),
            ]);

            if ($status === 'Approved') {
                $this->updateQuotaBasedOnRequest($attendanceRequest);
            }

            $this->logActivity(
                $status === 'Approved' ? 'APPROVE' : 'REJECT',
                "{$status} permintaan {$attendanceRequest->type} dari {$attendanceRequest->employee_name} ({$attendanceRequest->date_from->format('Y-m-d')})",
                'Presensi'
            );


            DB::commit();
            Log::info("Pengajuan Presensi ID: {$id} ({$attendanceRequest->type}) dari {$attendanceRequest->employee_name} berhasil diubah statusnya menjadi: {$status}.");

            return response()->json(['message' => "Permintaan berhasil di{$status}."], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing request ID {$id}: " . $e->getMessage());

            // Tangkap error kuota tidak cukup atau error database lainnya
            $errorMessage = str_contains($e->getMessage(), 'kuota') ? $e->getMessage() : 'Gagal memproses permintaan dan memotong kuota. Cek log server untuk detail.';

            return response()->json(['message' => $errorMessage, 'error_detail' => $e->getMessage()], 500);
        }
    }

    /**
     * Logika KRITIS untuk memotong kuota cuti/izin.
     */
    private function updateQuotaBasedOnRequest(AttendanceRequest $request)
    {
        if ($request->quota_deducted) {
             Log::warning("Kuota sudah pernah dikurangi untuk Request ID {$request->id}. Melewatkan pengurangan.");
             return;
        }

        $nopek = $request->nopek;
        $type = $request->type;
        // Tanggal sudah menjadi objek Carbon karena dari model (Casts)
        $daysTaken = $this->getWorkingDays($request->date_from->format('Y-m-d'), $request->date_to->format('Y-m-d'));

        if ($daysTaken === 0) {
            Log::warning("Request ID {$request->id} (Type: {$type}) mengambil 0 hari kerja. Tidak ada kuota yang dipotong.");
            $request->update(['quota_deducted' => true]);
            return;
        }

        try {
            switch ($type) {
                // KASUS 1: IZIN CUTI / CUTI TAHUNAN -> Kurangi LeaveQuota::leave_taken
                case 'Izin Cuti':
                case 'Cuti Tahunan':
                    // Menggunakan LeaveQuota untuk cuti tahunan
                    $quota = LeaveQuota::firstOrCreate(
                        ['nopek' => $nopek],
                        ['annual_quota' => 12, 'leave_taken' => 0, 'sick_leave_taken' => 0]
                    );

                    $sisaCuti = $quota->annual_quota - $quota->leave_taken;

                    if ($sisaCuti < $daysTaken) {
                         throw new \Exception("Kuota cuti tahunan tidak cukup. Tersisa: " . $sisaCuti . " hari.");
                    }

                    $quota->leave_taken += $daysTaken;
                    $quota->save();
                    Log::info("Kuota Cuti Tahunan untuk {$nopek} dipotong sebanyak {$daysTaken} hari (Total diambil: {$quota->leave_taken}).");
                    break;

                // KASUS 2: IZIN SAKIT / IZIN KHUSUS -> Kurangi PermitQuota::permit_taken
                case 'Izin Sakit':
                case 'Izin Khusus':
                    // Menggunakan PermitQuota untuk izin khusus/sakit
                    $quota = PermitQuota::firstOrCreate(
                        ['nopek' => $nopek],
                        ['permit_quota' => 5, 'permit_taken' => 0]
                    );

                    $sisaIzin = $quota->permit_quota - $quota->permit_taken;

                    if ($sisaIzin < $daysTaken) {
                         throw new \Exception("Kuota izin tidak cukup. Tersisa: " . $sisaIzin . " hari.");
                    }

                    $quota->permit_taken += $daysTaken;
                    $quota->save();
                    Log::info("Kuota Izin (Sakit/Khusus) untuk {$nopek} dipotong sebanyak {$daysTaken} hari (Total diambil: {$quota->permit_taken}).");
                    break;

                default:
                    Log::info("Pengajuan Tipe {$type} tidak memotong kuota cuti/izin.");
                    break;
            }

            // Tandai sudah dipotong
            $request->update(['quota_deducted' => true]);

        } catch (\Exception $e) {
            Log::error("FATAL: Gagal memotong kuota untuk NOP: {$nopek}, Tipe: {$type}. Error: " . $e->getMessage());
            throw $e;
        }
    }
}
