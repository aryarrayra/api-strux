<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KontrakDigital extends Model
{
    use HasFactory;

    protected $table = 'kontrak_digital';
    protected $primaryKey = 'id_kontrak';
    
    protected $fillable = [
        'id_sewa',
        'file_kontrak',
        'tanggal_tanda_tangan',
        'status_kontrak'
    ];

    public $timestamps = false;

    // Relationships
    public function penyewaan()
    {
        return $this->belongsTo(Penyewaan::class, 'id_sewa');
    }
}