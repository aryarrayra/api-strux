<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Petugas extends Model
{
    use HasFactory;

    protected $table = 'petugas';
    protected $primaryKey = 'id_petugas';
    
    protected $fillable = [
        'nama_petugas',
        'no_telp',
        'alamat',
        'tempat_lahir',
        'tanggal_lahir', // TAMBAHKAN INI
        'role',
        'email',
        'status'
    ];

    // TAMBAHKAN CASTS UNTUK MEMASTIKAN TIPE DATA
    protected $casts = [
        'nama_petugas' => 'string',
        'no_telp' => 'string', 
        'alamat' => 'string',
        'tempat_lahir' => 'string',
        'tanggal_lahir' => 'date',
        'role' => 'string',
        'email' => 'string',
        'status' => 'string'
    ];

    public $timestamps = false;

    // Relationships
    public function jadwal()
    {
        return $this->hasMany(JadwalSewa::class, 'id_petugas');
    }
}