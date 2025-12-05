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
        'alamat',
        'tempat_lahir',
        'tanggal_lahir', // TAMBAHKAN INI
        'role',
        'email',
        'password',
        'status'
    ];

    // TAMBAHKAN CASTS UNTUK MEMASTIKAN TIPE DATA
    protected $casts = [
        'nama_petugas' => 'string',
        'no_telp' => 'string', 
        'alamat' => 'string',
        'tempat_lahir' => 'string',
        'tanggal_lahir' => 'date',
        'role' => 'string',
        'email' => 'string',
        'password' => 'string',
        'status' => 'string'
    ];

    public $timestamps = false;

    // Relationships
    public function jadwal()
    {
        return $this->hasMany(JadwalSewa::class, 'id_petugas');
    }

        public function checkPassword($inputPassword)
    {
        // Log untuk debugging
        \Log::info('Checking password:', [
            'input' => $inputPassword,
            'stored' => $this->password,
            'stored_length' => strlen($this->password)
        ]);
        
        // 1. Jika password di DB adalah plain text
        if ($this->password === $inputPassword) {
            \Log::info('Password match (plain text)');
            return true;
        }
        
        // 2. Jika di DB sudah di-hash (fallback untuk legacy)
        if (Hash::check($inputPassword, $this->password)) {
            \Log::info('Password match (hashed)');
            return true;
        }
        
        \Log::warning('Password tidak cocok');
        return false;
    }
}