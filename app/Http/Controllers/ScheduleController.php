<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Traits\LogsActivity;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    use LogsActivity;

    /**
     * Menampilkan daftar semua jadwal/roster.
     */
    public function index(Request $request)
    {
        try {
            $query = Schedule::query();

            // --- Implementasi Filter dan Search ---
            if ($request->filled('start_date_filter')) {
                $query->where('end_date', '>=', $request->input('start_date_filter'));
            }
            if ($request->filled('end_date_filter')) {
                $query->where('start_date', '<=', $request->input('end_date_filter'));
            }

            if ($request->filled('shift_type')) {
                $query->where('shift_type', $request->input('shift_type'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('search')) {
                $searchTerm = strtolower($request->input('search'));
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('supervisor', 'like', "%{$searchTerm}%")
                        ->orWhere('schedule_name', 'like', "%{$searchTerm}%")
                        ->orWhere('employee_nipeks', 'like', "%\"{$searchTerm}\"%");
                });
            }

            $schedules = $query->orderBy('start_date', 'desc')->get();

            return response()->json($schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'startDate' => $schedule->start_date->toDateString(),
                    'endDate' => $schedule->end_date->toDateString(),
                    'shiftType' => $schedule->shift_type,
                    'workSystem' => $schedule->work_system,
                    'supervisor' => $schedule->supervisor,
                    'schedule' => $schedule->schedule,
                    'scheduleName' => $schedule->schedule_name ?? '',
                    'employeeNipeks' => $schedule->employee_nipeks,
                    'status' => $schedule->status,
                ];
            }));
        } catch (\Exception $e) {
            Log::error("Schedule fetch error: " . $e->getMessage());
            return response()->json([], 200);
        }
    }

    /**
     * Menyimpan roster/jadwal baru (Roster Block).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
                'shift_type' => 'required|in:Pagi,Sore,Malam,Non-Shift,Libur',
                'work_system' => 'required|in:Shift,Non-Shift',
                'supervisor' => 'required|string|max:255',
                'schedule' => 'required|string|max:255',
                'schedule_name' => 'required|string|max:255',
                'employee_nipeks' => 'required|array',
                'status' => 'nullable|in:Aktif,Mendatang,Selesai',
            ]);

            $schedule = Schedule::create([
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'shift_type' => $validated['shift_type'],
                'work_system' => $validated['work_system'],
                'supervisor' => $validated['supervisor'],
                'schedule' => $validated['schedule'],
                'schedule_name' => $validated['schedule_name'],
                'employee_nipeks' => $validated['employee_nipeks'],
                'status' => $validated['status'] ?? 'Mendatang',
            ]);

            $count = count($validated['employee_nipeks']);

            $this->logActivity(
                'CREATE',
                "Menambah Roster {$validated['shift_type']} ({$validated['schedule_name']}) untuk {$count} karyawan. Berlaku {$validated['startDate']} s/d {$validated['endDate']}.",
                'Jadwal'
            );

            return response()->json($schedule, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Schedule store error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan roster. Cek log server.'], 500);
        }
    }

    /**
     * Memperbarui jadwal/roster.
     */
    public function update(Request $request, $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);

            $oldShiftType = $schedule->shift_type;
            $oldStartDate = $schedule->start_date->toDateString();

            $validated = $request->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after_or_equal:startDate',
                'shift_type' => 'required|in:Pagi,Sore,Malam,Non-Shift,Libur',
                'work_system' => 'required|in:Shift,Non-Shift',
                'supervisor' => 'required|string|max:255',
                'schedule' => 'required|string|max:255',
                'schedule_name' => 'required|string|max:255',
                'employee_nipeks' => 'required|array',
                'status' => 'required|in:Aktif,Mendatang,Selesai',
            ]);

            $schedule->update([
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'shift_type' => $validated['shift_type'],
                'work_system' => $validated['work_system'],
                'supervisor' => $validated['supervisor'],
                'schedule' => $validated['schedule'],
                'schedule_name' => $validated['schedule_name'],
                'employee_nipeks' => $validated['employee_nipeks'],
                'status' => $validated['status'],
            ]);

            $this->logActivity(
                'UPDATE',
                "Memperbarui Roster ID {$id}. Perubahan: {$oldShiftType} ({$oldStartDate}) menjadi {$validated['shift_type']} ({$validated['startDate']}).",
                'Jadwal'
            );

            return response()->json($schedule, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Roster tidak ditemukan.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Schedule update error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal memperbarui roster. Cek log server.'], 500);
        }
    }

    /**
     * Menghapus jadwal/roster.
     */
    public function destroy($id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $shiftType = $schedule->shift_type;
            $startDate = $schedule->start_date->toDateString();

            $schedule->delete();

            $this->logActivity(
                'DELETE',
                "Menghapus Roster {$shiftType} yang dimulai {$startDate} (ID: {$id}).",
                'Jadwal'
            );

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Schedule delete error: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus roster.'], 500);
        }
    }

    /**
     * Quick Shift (Daily Update)
     */
public function updateDailyShift(Request $request)
{
    try {
        Log::info('Quick Shift Request Data:', $request->all());

        $validated = $request->validate([
            'employee_nipeks' => 'required|array',
            'shift_type' => 'required|in:Pagi,Sore,Malam,Non-Shift,Libur',
            'supervisor' => 'required|string|max:255',
            'schedule_name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'work_system' => 'required|in:Shift,Non-Shift',
            'schedule' => 'required|string|max:255',
        ]);

        $employees_nipeks = $validated['employee_nipeks'];
        $targetDate = $validated['date'];
        $employees_count = count($employees_nipeks);
        $updatedNipeks = [];

        if ($employees_count === 0) {
            return response()->json(['message' => 'Tidak ada karyawan yang dipilih untuk Quick Shift.'], 400);
        }

        foreach ($employees_nipeks as $nopek) {
            // 1. Cari record harian yang sudah ada untuk nopek ini
            $quickSchedule = Schedule::where('start_date', $targetDate)
                ->where('end_date', $targetDate)
                ->whereJsonContains('employee_nipeks', $nopek) // Cari roster harian yang mengandung nopek ini
                ->first();

$dataToSave = [
    'start_date' => $targetDate,
    'end_date' => $targetDate,
    'shift_type' => $validated['shift_type'],
    'work_system' => $validated['work_system'],
    'supervisor' => $validated['supervisor'],
    'schedule' => $validated['schedule'], // Ini akan menyimpan apapun yang diketik user
    'schedule_name' => $validated['schedule_name'],
    'status' => 'Aktif',
    'employee_nipeks' => [$nopek],
];
            if ($quickSchedule) {
                // Perbarui record yang ada
                $quickSchedule->update($dataToSave);
                $updatedNipeks[] = $nopek;
            } else {
                // Buat record baru
                Schedule::create($dataToSave);
                $updatedNipeks[] = $nopek;
            }
        }

        $message = "Quick Shift ({$validated['shift_type']}) berhasil diterapkan ke {$employees_count} karyawan untuk tanggal {$targetDate}.";

        $this->logActivity('QUICK_UPDATE', $message, 'Jadwal');

        return response()->json(['message' => $message, 'updated_count' => $employees_count], 200);

    } catch (ValidationException $e) {
        Log::error("VALIDASI GAGAL (422) pada Quick Shift:", $e->errors());
        return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        // Ini adalah tempat error 500 sering terjadi
        Log::error("Gagal menjalankan Daily Shift Update: " . $e->getMessage() . " di baris " . $e->getLine());
        return response()->json(['message' => 'Gagal menyimpan Quick Shift. Cek log server.'], 500);
    }
}
}
