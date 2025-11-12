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
    'penyewaan' => PenyewaanController::class,
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
Route::get('alat-berat/status/{status}', [AlatBeratController::class, 'getByStatus']);
Route::get('penyewaan/pelanggan/{id}', [PenyewaanController::class, 'getByPelanggan']);
Route::get('pembayaran/sewa/{id}', [PembayaranController::class, 'getBySewa']);
Route::get('notifikasi/admin/{id}', [NotifikasiController::class, 'getByAdmin']);
Route::get('favorit/pelanggan/{id}', [FavoritPelangganController::class, 'getByPelanggan']);
Route::post('penyewaan/{id}/rating', [PenyewaanController::class, 'addRating']);
Route::post('/admin/login', [AdminController::class, 'login']);

// ==================== ROUTES PERSETUJUAN PINJAMAN ====================
Route::get('/penyewaan/persetujuan/pending', [PenyewaanController::class, 'getPersetujuanPinjaman']);
Route::get('/penyewaan/persetujuan/history', [PenyewaanController::class, 'getHistoryPersetujuan']);
Route::post('/penyewaan/{id}/persetujuan', [PenyewaanController::class, 'approvePinjaman']);

// Custom routes dokumen
Route::get('/dokumen-pinjaman/download/{id}', [DokumenPinjamanController::class, 'download']);
Route::get('/dokumen-pinjaman/preview/{id}', [DokumenPinjamanController::class, 'preview']);
Route::get('/dokumen-pinjaman/sewa/{id}', [DokumenPinjamanController::class, 'getBySewa']);
Route::get('/dokumen-pinjaman/tipe/{tipe}', [DokumenPinjamanController::class, 'getByTipe']);
Route::post('/dokumen-pinjaman/upload-multiple', [DokumenPinjamanController::class, 'uploadMultiple']);