<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AlatBeratController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\PenyewaanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PetugasController;
use App\Http\Controllers\PerawatanAlatController;
use App\Http\Controllers\JadwalSewaController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\KontrakDigitalController;
use App\Http\Controllers\SessionPelangganController;
use App\Http\Controllers\LogAktivitasPelangganController;
use App\Http\Controllers\KonfigurasiController;
use App\Http\Controllers\FavoritPelangganController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DokumenPinjamanController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check route - TEST INI DULU!
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API Sewa Alat Berat is running',
        'timestamp' => now()->toDateTimeString(),
        'version' => '1.0.0'
    ]);
});

// Public API Routes
Route::apiResources([
    'alat-berat' => AlatBeratController::class,
    'pelanggan' => PelangganController::class,
    'pembayaran' => PembayaranController::class,
    'admin' => AdminController::class,
    'petugas' => PetugasController::class,
    'perawatan-alat' => PerawatanAlatController::class,
    'jadwal-sewa' => JadwalSewaController::class,
    'notifikasi' => NotifikasiController::class,
    'kontrak-digital' => KontrakDigitalController::class,
    'session-pelanggan' => SessionPelangganController::class,
    'log-aktivitas' => LogAktivitasPelangganController::class,
    'konfigurasi' => KonfigurasiController::class,
    'favorit-pelanggan' => FavoritPelangganController::class,
    'dokumen-pinjaman'=> DokumenPinjamanController::class,
]);

// Custom routes

Route::get('penyewaan/pelanggan/{id}', [PenyewaanController::class, 'getByPelanggan']);
Route::get('pembayaran/sewa/{id}', [PembayaranController::class, 'getBySewa']);
Route::get('notifikasi/admin/{id}', [NotifikasiController::class, 'getByAdmin']);
Route::get('favorit/pelanggan/{id}', [FavoritPelangganController::class, 'getByPelanggan']);
Route::post('penyewaan/{id}/rating', [PenyewaanController::class, 'addRating']);

Route::get('/pelanggan/by-user/{id_user}', [PelangganController::class, 'getPelangganByUserId']);
Route::post('/pelanggan/by-user', [PelangganController::class, 'getPelangganByUser']);


// ==================== ROUTES PERSETUJUAN PINJAMAN ====================
Route::get('/penyewaan/persetujuan/pending', [PenyewaanController::class, 'getPersetujuanPinjaman']);
Route::get('/penyewaan/persetujuan/history', [PenyewaanController::class, 'getHistoryPersetujuan']);
Route::post('/penyewaan/{id}/persetujuan', [PenyewaanController::class, 'approvePinjaman']);

//auth
Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/petugas/login', [PetugasController::class, 'login']);
Route::post('/user/login', [PelangganController::class, 'login']);
Route::post('/user/register', [PelangganController::class, 'register']);
Route::post('/user/logout', [PelangganController::class, 'logout']);
Route::post('/user/profile', [PelangganController::class, 'profile']);
Route::post('/user/profile/update', [PelangganController::class, 'updateProfile']);
Route::post('/upload-ktp', [PelangganController::class, 'uploadKtp']);  
Route::post('/upload-profile-photo', [PelangganController::class, 'uploadProfilePhoto']);

//alat-berat
Route::prefix('alat-berat')->group(function () {
    Route::get('/', [AlatBeratController::class, 'index']); // GET all
    Route::post('/', [AlatBeratController::class, 'store']); // POST create
    Route::get('/search', [AlatBeratController::class, 'search']); // GET search
    Route::get('/status/{status}', [AlatBeratController::class, 'getByStatus']); // GET by status
    Route::get('/{id}', [AlatBeratController::class, 'show']); // GET single
    Route::put('/{id}', [AlatBeratController::class, 'update']); // PUT update
    Route::delete('/{id}', [AlatBeratController::class, 'destroy']); // DELETE
    // Tambahkan route untuk upload foto
    Route::post('/upload-foto', [AlatBeratController::class, 'uploadFoto']);
});

// Admin Dashboard Routes
Route::prefix('admin/dashboard')->group(function () {
    Route::get('stats', [AdminDashboardController::class, 'getDashboardStats']);
    Route::get('activities', [AdminDashboardController::class, 'getRecentActivities']);
    Route::get('maintenance', [AdminDashboardController::class, 'getMaintenanceSchedule']);
    Route::get('revenue-chart', [AdminDashboardController::class, 'getRevenueChart']);
    Route::get('alat-utilization', [AdminDashboardController::class, 'getAlatUtilization']);
    Route::get('quick-stats', [AdminDashboardController::class, 'getQuickStats']);
    Route::get('pending-approvals', [AdminDashboardController::class, 'getPendingApprovals']);
    Route::get('all', [AdminDashboardController::class, 'getAllDashboardData']);
});

// ✅ ROUTES PENYEWAAN YANG BENAR
Route::prefix('penyewaan')->group(function () {
    // Basic CRUD (sudah tercover oleh apiResources, tapi kita custom)
    Route::get('/', [PenyewaanController::class, 'index']);
    Route::post('/', [PenyewaanController::class, 'store']);
    Route::get('/{id}', [PenyewaanController::class, 'show']);
    Route::put('/{id}', [PenyewaanController::class, 'update']);
    Route::delete('/{id}', [PenyewaanController::class, 'destroy']);
    
    // Custom routes
    Route::get('/pelanggan/{id}', [PenyewaanController::class, 'getByPelanggan']);
    Route::get('/persetujuan-pinjaman', [PenyewaanController::class, 'getPersetujuanPinjaman']);
    Route::post('/{id}/approve', [PenyewaanController::class, 'approvePinjaman']); // ✅ INI YANG DIPAKAI
    Route::post('/{id}/rating', [PenyewaanController::class, 'addRating']);

    Route::post('/{id}/upload-dokumen', [PenyewaanController::class, 'uploadDokumen']);
    Route::get('/{id}/dokumen', [PenyewaanController::class, 'getDokumenPenyewaan']);
    Route::get('/dokumen/{idDokumen}/view', [PenyewaanController::class, 'viewDokumen']);
});


Route::prefix('dokumen-pinjaman')->group(function () {
    Route::get('/', [DokumenPinjamanController::class, 'index']);
    Route::post('/', [DokumenPinjamanController::class, 'store']);
    Route::get('/{id}', [DokumenPinjamanController::class, 'show']);
    Route::put('/{id}', [DokumenPinjamanController::class, 'update']);
    Route::delete('/{id}', [DokumenPinjamanController::class, 'destroy']);
    
    // Custom routes
    Route::get('/download/{id}', [DokumenPinjamanController::class, 'download']);
    Route::get('/preview/{id}', [DokumenPinjamanController::class, 'preview']);
    Route::get('/sewa/{id}', [DokumenPinjamanController::class, 'getBySewa']);
    Route::get('/tipe/{tipe}', [DokumenPinjamanController::class, 'getByTipe']);
    Route::post('/upload-multiple', [DokumenPinjamanController::class, 'uploadMultiple']);
    
    // ✅ TAMBAHKAN INI - ROUTE YANG DIPAKAI MOBILE APP
    Route::post('/upload-for-penyewaan', [DokumenPinjamanController::class, 'uploadForPenyewaan']);
});

Route::prefix('pembayaran')->group(function () {

    Route::get('/export-laporan', [PembayaranController::class, 'exportLaporan']);
    Route::get('/test-laporan', [PembayaranController::class, 'testLaporan']);

    Route::get('/', [PembayaranController::class, 'index']);
    Route::post('/', [PembayaranController::class, 'store']);
    Route::get('/{id}', [PembayaranController::class, 'show']);
    Route::put('/{id}', [PembayaranController::class, 'update']);
    Route::delete('/{id}', [PembayaranController::class, 'destroy']);
    
    // Tambahkan routes baru untuk laporan keuangan

});

Route::get('/laporan-keuangan', [PembayaranController::class, 'getLaporanKeuangan']); // Original
Route::get('/pembayaran/laporan-simple', [PembayaranController::class, 'getLaporanKeuanganSimple']); // Simple version
Route::get('/pembayaran/laporan-safe', [PembayaranController::class, 'getLaporanKeuanganSafe']); // Safe version
Route::get('/pembayaran/laporan-raw', [PembayaranController::class, 'getLaporanKeuanganRaw']); // Raw SQL version
Route::get('/pembayaran/test-relasi', [PembayaranController::class, 'testRelasi']); // Test relationships
Route::get('/pembayaran/download-pdf', [PembayaranController::class, 'downloadPDF']);