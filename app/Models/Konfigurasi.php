<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Konfigurasi extends Model
{
    use HasFactory;

    protected $table = 'konfigurasi';
    protected $primaryKey = 'id_konfigurasi';
    
    protected $fillable = [
        'nama_setting',
        'nilai_setting',
        'keterangan'
    ];

    public $timestamps = false;
}