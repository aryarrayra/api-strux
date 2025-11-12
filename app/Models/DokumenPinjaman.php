<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumenPinjaman extends Model
{
    use HasFactory;

    protected $table = 'dokumen_pinjaman';
    protected $primaryKey = 'id_dokumen';
    
    protected $fillable = [
        'id_sewa',
        'nama_dokumen',
        'file_path',
        'tipe_dokumen',
        'ukuran_file',
        'uploaded_by'
    ];

    protected $casts = [
        'ukuran_file' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relasi ke penyewaan
    public function penyewaan()
    {
        return $this->belongsTo(Penyewaan::class, 'id_sewa', 'id_sewa');
    }

    // Relasi ke admin yang upload
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'uploaded_by', 'id_admin');
    }

    // Relasi ke pelanggan (jika diupload pelanggan)
    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'uploaded_by', 'id_pelanggan');
    }
}