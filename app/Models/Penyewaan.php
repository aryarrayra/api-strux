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
        'rating',
        'ulasan',
        'created_at',
        'updated_at'
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
}