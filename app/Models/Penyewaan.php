<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penyewaan extends Model
{
    use HasFactory;

    protected $table = 'penyewaan';
    protected $primaryKey = 'id_sewa';
    
    protected $fillable = [
        'id_pelanggan',
        'id_alat',
        'tanggal_sewa',
        'tanggal_kembali',
        'total_harga',
        'status_sewa',
        'dokumen_data',
        'rating',
        'ulasan',
        'status_persetujuan', // TAMBAHKAN INI
        'disetujui_oleh',     // TAMBAHKAN INI
        'tanggal_persetujuan', // TAMBAHKAN INI
        'alasan_penolakan',   // TAMBAHKAN INI
        'created_at',
        'updated_at',
        'nama_proyek',
        'lokasi_proyek', 
        'deskripsi_proyek',
        'latitude',
        'longitude'
    ];

    public $timestamps = true;
    
    // Relationships
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan');
    }

    public function alat()
    {
        return $this->belongsTo(AlatBerat::class, 'id_alat');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'id_sewa');
    }

    public function jadwal()
    {
        return $this->hasMany(JadwalSewa::class, 'id_sewa');
    }

    public function kontrak()
    {
        return $this->hasMany(KontrakDigital::class, 'id_sewa');
    }

    // ✅ TAMBAHKAN RELATIONSHIP DOKUMEN
    public function dokumen()
    {
        return $this->hasMany(DokumenPinjaman::class, 'id_sewa');
    }

    // ✅ Relationship ke admin yang approve
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'disetujui_oleh', 'id_admin');
    }
}