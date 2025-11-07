<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlatBerat extends Model
{
    use HasFactory;

    protected $table = 'alat_berat';
    protected $primaryKey = 'id_alat';
    
    protected $fillable = [
        'nama_alat',
        'jenis',
        'kapasitas',
        'harga_sewa_per_hari',
        'status',
        'deskripsi',
        'foto',
        'lokasi',
        'latitude',
        'longitude',
        'created_at',
        'updated_at'
    ];

    public $timestamps = true; // Karena ada created_at & updated_at

    // Relationships
    public function penyewaan()
    {
        return $this->hasMany(Penyewaan::class, 'id_alat');
    }

    public function perawatan()
    {
        return $this->hasMany(PerawatanAlat::class, 'id_alat');
    }

    public function favorit()
    {
        return $this->hasMany(FavoritPelanggan::class, 'id_alat');
    }
}