<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAktivitasPelanggan extends Model
{
    use HasFactory;

    protected $table = 'log_aktivitas_pelanggan';
    protected $primaryKey = 'id_log';
    
    protected $fillable = [
        'id_pelanggan',
        'aktivitas',
        'deskripsi',
        'id_referensi',
        'waktu',
        'ip_address',
        'user_agent'
    ];

    public $timestamps = false;

    // Relationships
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan');
    }
}