<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionPelanggan extends Model
{
    use HasFactory;

    protected $table = 'sessions_pelanggan';
    protected $primaryKey = 'id_session';
    
    protected $fillable = [
        'id_pelanggan',
        'token',
        'expires_at',
        'created_at',
        'last_activity',
        'device_type',
        'fcm_token'
        // Tidak ada: updated_at
    ];

    public $timestamps = false;

    // Relationships
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan');
    }
}