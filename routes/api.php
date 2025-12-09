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

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API Sewa Alat Berat is running',
        'timestamp' => now()->toDateTimeString(),
        'version' => '1.0.0'
    ]);
});

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

Route::get('penyewaan/pelanggan/{id}', [PenyewaanController::class, 'getByPelanggan']);
Route::get('pembayaran/sewa/{id}', [PembayaranController::class, 'getBySewa']);
Route::get('notifikasi/admin/{id}', [NotifikasiController::class, 'getByAdmin']);
Route::get('favorit/pelanggan/{id}', [FavoritPelangganController::class, 'getByPelanggan']);
Route::post('penyewaan/{id}/rating', [PenyewaanController::class, 'addRating']);

Route::get('/pelanggan/by-user/{id_user}', [PelangganController::class, 'getPelangganByUserId']);
Route::post('/pelanggan/by-user', [PelangganController::class, 'getPelangganByUser']);

Route::get('/penyewaan/persetujuan/pending', [PenyewaanController::class, 'getPersetujuanPinjaman']);
Route::get('/penyewaan/persetujuan/history', [PenyewaanController::class, 'getHistoryPersetujuan']);
Route::post('/penyewaan/{id}/persetujuan', [PenyewaanController::class, 'approvePinjaman']);

Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/petugas/login', [PetugasController::class, 'login']);
Route::post('/user/login', [PelangganController::class, 'login']);
Route::post('/user/register', [PelangganController::class, 'register']);
Route::post('/user/logout', [PelangganController::class, 'logout']);
Route::post('/user/profile', [PelangganController::class, 'profile']);
Route::post('/user/profile/update', [PelangganController::class, 'updateProfile']);
Route::post('/upload-ktp', [PelangganController::class, 'uploadKtp']);
Route::post('/upload-profile-photo', [PelangganController::class, 'uploadProfilePhoto']);

Route::prefix('alat-berat')->group(function () {
    Route::get('/', [AlatBeratController::class, 'index']);
    Route::post('/', [AlatBeratController::class, 'store']);
    Route::get('/search', [AlatBeratController::class, 'search']);
    Route::get('/status/{status}', [AlatBeratController::class, 'getByStatus']);
    Route::get('/{id}', [AlatBeratController::class, 'show']);
    Route::put('/{id}', [AlatBeratController::class, 'update']);
    Route::delete('/{id}', [AlatBeratController::class, 'destroy']);
    Route::post('/upload-foto', [AlatBeratController::class, 'uploadFoto']);
});

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

Route::prefix('penyewaan')->group(function () {
    Route::get('/', [PenyewaanController::class, 'index']);
    Route::post('/', [PenyewaanController::class, 'store']);
    Route::get('/{id}', [PenyewaanController::class, 'show']);
    Route::put('/{id}', [PenyewaanController::class, 'update']);
    Route::delete('/{id}', [PenyewaanController::class, 'destroy']);
    
    Route::get('/pelanggan/{id}', [PenyewaanController::class, 'getByPelanggan']);
    Route::get('/persetujuan-pinjaman', [PenyewaanController::class, 'getPersetujuanPinjaman']);
    Route::post('/{id}/approve', [PenyewaanController::class, 'approvePinjaman']);
    Route::post('/{id}/rating', [PenyewaanController::class, 'addRating']);
    Route::post('/{id}/upload-dokumen', [PenyewaanController::class, 'uploadDokumen']);
    Route::get('/{id}/dokumen', [PenyewaanController::class, 'getDokumenPenyewaan']);
    Route::get('/dokumen/{idDokumen}/view', [PenyewaanController::class, 'viewDokumen']);
    Route::put('/{id}/selesai', [PenyewaanController::class, 'selesai']);
});

Route::prefix('dokumen-pinjaman')->group(function () {
    Route::get('/', [DokumenPinjamanController::class, 'index']);
    Route::post('/', [DokumenPinjamanController::class, 'store']);
    Route::get('/{id}', [DokumenPinjamanController::class, 'show']);
    Route::put('/{id}', [DokumenPinjamanController::class, 'update']);
    Route::delete('/{id}', [DokumenPinjamanController::class, 'destroy']);
    Route::get('/download/{id}', [DokumenPinjamanController::class, 'download']);
    Route::get('/preview/{id}', [DokumenPinjamanController::class, 'preview']);
    Route::get('/sewa/{id}', [DokumenPinjamanController::class, 'getBySewa']);
    Route::get('/tipe/{tipe}', [DokumenPinjamanController::class, 'getByTipe']);
    Route::post('/upload-multiple', [DokumenPinjamanController::class, 'uploadMultiple']);
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
});

Route::get('/laporan-keuangan', [PembayaranController::class, 'getLaporanKeuangan']);
Route::get('/pembayaran/laporan-simple', [PembayaranController::class, 'getLaporanKeuanganSimple']);
Route::get('/pembayaran/laporan-safe', [PembayaranController::class, 'getLaporanKeuanganSafe']);
Route::get('/pembayaran/laporan-raw', [PembayaranController::class, 'getLaporanKeuanganRaw']); 
Route::get('/pembayaran/download-pdf', [PembayaranController::class, 'downloadPDF']);