<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';
    protected $primaryKey = 'id_pembayaran';
    
    protected $fillable = [
        'id_sewa',
        'tanggal_bayar',
        'jumlah_bayar',
        'metode',
        'status_pembayaran',
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

    // Relationships
    public function penyewaan()
    {
        return $this->belongsTo(Penyewaan::class, 'id_sewa');
    }
}