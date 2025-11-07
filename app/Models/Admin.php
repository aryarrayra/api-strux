<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    protected $table = 'admin';
    protected $primaryKey = 'id_admin';
    
    protected $fillable = [
        'username',
        'password',
        'nama_admin',
        'level',
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

    // Relationships
    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'id_admin');
    }
}