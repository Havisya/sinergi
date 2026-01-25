<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use App\Traits\LogsActivity; // <<< TAMBAHKAN

class CompanySettingController extends Controller
{
    use LogsActivity; // <<< GUNAKAN TRAIT

    // GET /api/settings/company (DIBIARKAN SAMA)
    public function index()
    {
        $settings = CompanySetting::firstOrNew([]);

        return response()->json([
            'companyName' => $settings->company_name ?? '',
            'standardWorkingHours' => $settings->standard_working_hours ?? '',
            'lateToleranceMinutes' => (int)($settings->late_tolerance_minutes ?? 0),
            'dailyCheckInLimit' => $settings->daily_check_in_limit
                ? date('H:i', strtotime($settings->daily_check_in_limit))
                : '08:00', // default jika null
        ]);
    }

    // PUT /api/settings/company
    public function update(Request $request)
    {
        $validated = $request->validate([
            'companyName' => 'required|string|max:255',
            'standardWorkingHours' => 'required|string|max:50',
            'lateToleranceMinutes' => 'required|integer|min:0|max:60',
            'dailyCheckInLimit' => 'required|date_format:H:i',
        ]);

        $settings = CompanySetting::firstOrNew([]);
        $isNew = !$settings->exists;
        $oldName = $settings->company_name;

        $settings->company_name = $validated['companyName'];
        $settings->standard_working_hours = $validated['standardWorkingHours'];
        $settings->late_tolerance_minutes = $validated['lateToleranceMinutes'];
        $settings->daily_check_in_limit = $validated['dailyCheckInLimit'] . ':00';

        $settings->save();

        // --- LOG AKTIVITAS ---
        $action = $isNew ? 'CREATE' : 'UPDATE';
        $details = $isNew
            ? "Menetapkan Pengaturan Perusahaan awal (Nama: {$settings->company_name})"
            : "Memperbarui Pengaturan Perusahaan (Toleransi Telat: {$settings->late_tolerance_minutes}m)";

        $this->logActivity($action, $details, 'Pengaturan');
        // ---------------------

        return response()->json([
            'message' => 'Pengaturan perusahaan berhasil disimpan!',
            'data' => [
                'companyName' => $settings->company_name,
                'standardWorkingHours' => $settings->standard_working_hours,
                'lateToleranceMinutes' => (int)$settings->late_tolerance_minutes,
                'dailyCheckInLimit' => date('H:i', strtotime($settings->daily_check_in_limit)),
            ]
        ]);
    }
}
