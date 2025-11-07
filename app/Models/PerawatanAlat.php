<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerawatanAlat extends Model
{
    use HasFactory;

    protected $table = 'perawatan_alat';
    protected $primaryKey = 'id_perawatan';
    
    protected $fillable = [
        'id_alat',
        'tanggal_perawatan',
        'keterangan',
        'biaya_perawatan',
        'status',
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

    // Relationships
    public function alat()
    {
        return $this->belongsTo(AlatBerat::class, 'id_alat');
    }
}