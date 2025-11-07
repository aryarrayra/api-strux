<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalSewa extends Model
{
    use HasFactory;

    protected $table = 'jadwal_sewa';
    protected $primaryKey = 'id_jadwal';
    
    protected $fillable = [
        'id_sewa',
        'tanggal_mulai',
        'tanggal_selesai',
        'lokasi_pengiriman',
        'lokasi_pengambilan', // Beda: pengambilan bukan pengembalian
        'status_jadwal',
        'id_petugas'
    ];

    public $timestamps = false; // Tidak ada created_at & updated_at

    // Relationships
    public function penyewaan()
    {
        return $this->belongsTo(Penyewaan::class, 'id_sewa');
    }

    public function petugas()
    {
        return $this->belongsTo(Petugas::class, 'id_petugas');
    }
}