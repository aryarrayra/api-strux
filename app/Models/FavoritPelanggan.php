<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FavoritPelanggan extends Model
{
    use HasFactory;

    protected $table = 'favorit_pelanggan';
    protected $primaryKey = 'id_favorit';
    
    protected $fillable = [
        'id_pelanggan',
        'id_alat',
        'created_at'
    ];

    public $timestamps = false;

    // Relationships
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan');
    }

    public function alat()
    {
        return $this->belongsTo(AlatBerat::class, 'id_alat');
    }
}