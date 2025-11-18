<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelanggan extends Model
{
    use HasFactory;

    protected $table = 'pelanggan';
    protected $primaryKey = 'id_pelanggan';
    
    protected $fillable = [
        'nama_pelanggan',
        'no_ktp',
        'alamat',
        'no_telp',
        'email',
        'foto_ktp',
        'foto_profil',
        "company_name",
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

    // Relationships
    public function penyewaan()
    {
        return $this->hasMany(Penyewaan::class, 'id_pelanggan');
    }

    public function sessions()
    {
        return $this->hasMany(SessionPelanggan::class, 'id_pelanggan');
    }

    public function logAktivitas()
    {
        return $this->hasMany(LogAktivitasPelanggan::class, 'id_pelanggan');
    }

    public function favorit()
    {
        return $this->hasMany(FavoritPelanggan::class, 'id_pelanggan');
    }
}