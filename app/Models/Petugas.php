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
        'role',
        'status'
    ];

    public $timestamps = false;

    // Relationships
    public function jadwal()
    {
        return $this->hasMany(JadwalSewa::class, 'id_petugas');
    }
}