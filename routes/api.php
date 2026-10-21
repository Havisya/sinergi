<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\ScheduleShiftController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\KunjunganDinasController;
use App\Http\Controllers\AttendanceRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminProfileController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| RUTE PUBLIK (TANPA LOGIN)
|--------------------------------------------------------------------------
| Hanya untuk otentikasi dasar (login), request koreksi, dan clock-in/out.
*/

// Test koneksi API
Route::get('test-json', function () {
    return response()->json(['status' => 'success', 'message' => 'API is working']);
});

// Otentikasi
Route::post('/login', [LoginController::class, 'login'])->name('api.login.post');
Route::get('/login', function () {
    return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
})->name('login');
Route::post('/logout', [LoginController::class, 'logout']);

// Password Reset
Route::post('forgot-password/send-link', [PasswordResetController::class, 'sendResetLink']);
Route::post('forgot-password/reset', [PasswordResetController::class, 'reset']);

// Absensi Karyawan (Jika diizinkan tanpa Sanctum)
Route::get('/', [AbsensiController::class, 'index']); // Absensi index (mungkin untuk cek status server)
Route::post('clockin', [AbsensiController::class, 'clockIn']);
Route::post('clockout', [AbsensiController::class, 'clockOut']);
Route::post('absensi/roster-statuses', [AbsensiController::class, 'getRosterStatuses']);


// Data Karyawan (Akses publik terbatas, misalnya untuk dropdown/list nama)
Route::get('karyawan/names-list', [KaryawanController::class, 'getNamesList']);


/*
|--------------------------------------------------------------------------
| 🔐 RUTE TERLINDUNGI (WAJIB LOGIN DENGAN SANCTUM)
|--------------------------------------------------------------------------
| Semua rute administrasi, CRUD data master, dan fitur report HARUS di sini
| agar `auth()->user()` terisi dan `AuditLog` dapat mendeteksi user.
*/
Route::middleware('auth:sanctum')->group(function () {

    // Karyawan & Master Data
    Route::apiResource('karyawan', KaryawanController::class);
    Route::apiResource('schedules', ScheduleController::class);
    Route::post('schedules/daily-update', [ScheduleController::class, 'updateDailyShift']);
    Route::post('/schedules/bulk-import', [ScheduleController::class, 'bulkImport']);
    Route::apiResource('overtimes', OvertimeController::class);
    Route::apiResource('kunjungan-dinas', KunjunganDinasController::class);

    // Konfigurasi Shift
    Route::prefix('shifts')->group(function () {
        Route::get('schedules', [ScheduleShiftController::class, 'index']);
        Route::post('schedules', [ScheduleShiftController::class, 'storeOrUpdate']);
        Route::delete('schedules/{id}', [ScheduleShiftController::class, 'destroy']);
    });

    // Absensi Admin & Report
Route::prefix('absensi')->group(function () {
    // Rute untuk mendapatkan data absensi (index) bagi Admin
    Route::get('/', [AbsensiController::class, 'index']);
    Route::post('koreksi', [AbsensiController::class, 'ajukanKoreksi']);

    Route::get('/fingerprint-logs', [AbsensiController::class, 'getFingerprintLogs']);

        Route::prefix('reports')->group(function () {
            Route::get('recap', [AbsensiController::class, 'recapReport']);
            Route::get('daily-matrix', [AbsensiController::class, 'dailyMatrixReport']);
            Route::get('export-excel', [AbsensiController::class, 'exportExcel']);
            Route::get('export-daily-matrix', [AbsensiController::class, 'exportDailyMatrix']);
        });
    });

    // Cuti & Izin (Manajemen Kuota/Izin oleh Admin)
    Route::prefix('leave')->group(function () {
        // Manajemen Kuota
        Route::get('quotas', [LeaveController::class, 'indexQuotas']);
        Route::post('quotas', [LeaveController::class, 'storeOrUpdateQuota']);
        Route::delete('quotas/{nopek}', [LeaveController::class, 'destroyQuota']);

        // Manajemen Izin
        Route::get('permits', [LeaveController::class, 'indexPermits']);
        Route::post('permits', [LeaveController::class, 'storeOrUpdatePermit']);
        Route::delete('permits/{nopek}', [LeaveController::class, 'destroyPermit']);
    });

    // Approval Request
    Route::resource('absensi/approval-requests', AttendanceRequestController::class)->except(['show']);    Route::post('absensi/approve/{id}', [AttendanceRequestController::class, 'approve']);
    Route::post('absensi/reject/{id}', [AttendanceRequestController::class, 'reject']);
    Route::get('absensi/history', [AttendanceRequestController::class, 'indexHistory']);

    // Dashboard & Dukungan
    Route::get('/dashboard/stats', [DashboardController::class, 'getDashboardStats']);
    Route::get('/absensi/active-shifts', [AbsensiController::class, 'getActiveShifts']);
    Route::post('/support/submit-ticket', [SupportController::class, 'submitTicket']);

    // Setting Perusahaan & Lokasi
    Route::prefix('settings')->group(function () {
        Route::get('company', [CompanySettingController::class, 'index']);
        Route::put('company', [CompanySettingController::class, 'update']);

        Route::get('admins', [AdminController::class, 'index']);
        Route::get('locations', [LocationController::class, 'index']);

        Route::resource('admins', AdminController::class)->only(['store', 'update', 'destroy']);
        Route::resource('locations', LocationController::class)->only(['store', 'update', 'destroy']);
    });

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/categories', [AuditLogController::class, 'getCategories']);
    });

    // ADMIN AREA
    Route::prefix('admin')->group(function () {
        Route::get('/debug-auth-check', function () {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Otentikasi Gagal di Auth Check'], 401);
            }
            return response()->json([
                'message' => 'Autentikasi Berhasil.',
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        });

        // 📋 Profil Admin
        Route::get('/profile', [AdminProfileController::class, 'getProfile']);
        Route::post('/broadcast', [AdminProfileController::class, 'broadcastMessage']);
    });
});

// Debug akses model (Dibiarkan di luar untuk testing, tidak memengaruhi logging)
Route::get('/debug-profile-bypass-test', function () {
    $user = User::where('role', 'Super Admin')->first();
    if (!$user) {
        return response()->json(['message' => 'Admin not seeded.'], 404);
    }
    return response()->json([
        'message' => 'Jalur Model/DB Aman.',
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'last_login' => $user->last_login_at ?? null,
        ]
    ]);
});
