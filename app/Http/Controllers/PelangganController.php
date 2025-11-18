<?php
namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PelangganController extends BaseController
{
    protected $model = Pelanggan::class;
    protected $validationRules = [
        'nama_pelanggan' => 'required|string|max:255',
        'no_ktp' => 'nullable|string|max:20|unique:pelanggan,no_ktp',
        'alamat' => 'nullable|string',
        'no_telp' => 'nullable|string|max:15',
        'email' => 'nullable|email|unique:pelanggan,email'
    ];

    // Path untuk menyimpan gambar di assets/images
    protected $assetsPath;
    
    public function __construct()
    {
        // Tentukan path ke folder assets/images
        // Sesuaikan dengan struktur project Laravel Anda
        $this->assetsPath = public_path('assets/images');
        
        // Pastikan folder exists
        if (!file_exists($this->assetsPath . '/ktp_photos')) {
            mkdir($this->assetsPath . '/ktp_photos', 0755, true);
        }
        if (!file_exists($this->assetsPath . '/profile_photos')) {
            mkdir($this->assetsPath . '/profile_photos', 0755, true);
        }
    }

    // Helper method untuk menyimpan file ke assets/images
    protected function saveToAssets($file, $folder = 'ktp_photos')
    {
        $fileName = $folder . '_' . Str::random(20) . '_' . time() . '.' . $file->getClientOriginalExtension();
        $filePath = $this->assetsPath . '/' . $folder . '/' . $fileName;
        
        // Pindahkan file ke assets/images
        $file->move($this->assetsPath . '/' . $folder, $fileName);
        
        // Return path yang akan disimpan di database (relative path)
        return 'assets/images/' . $folder . '/' . $fileName;
    }

    // Helper method untuk menghapus file dari assets
    protected function deleteFromAssets($filePath)
    {
        $fullPath = public_path($filePath);
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
            return true;
        }
        return false;
    }

    // Helper method untuk mendapatkan URL lengkap
    protected function getAssetUrl($filePath)
    {
        if (!$filePath) return null;
        
        // Jika sudah full URL, return langsung
        if (filter_var($filePath, FILTER_VALIDATE_URL)) {
            return $filePath;
        }
        
        // Jika relative path, buat full URL
        return url($filePath);
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string|min:6',
            ]);

            // Cari user berdasarkan username atau email
            $user = User::where('username', $validated['username'])
                       ->orWhere('email', $validated['username'])
                       ->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah',
                ], 401);
            }

            // BUAT TOKEN - PASTIKAN BAGIAN INI ADA
            $token = $user->createToken('auth-token')->plainTextToken;

            // Cari data pelanggan berdasarkan email
            $pelanggan = Pelanggan::where('email', $user->email)->first();

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ],
                    'pelanggan' => $pelanggan ? [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'nama_pelanggan' => $pelanggan->nama_pelanggan,
                        'email' => $pelanggan->email,
                        'no_telp' => $pelanggan->no_telp,
                        'alamat' => $pelanggan->alamat,
                        'company_name' => $pelanggan->company_name,
                        'foto_ktp' => $this->getAssetUrl($pelanggan->foto_ktp),
                        'foto_profil' => $this->getAssetUrl($pelanggan->foto_profil),
                    ] : null,
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // User registration method dengan upload foto KTP ke assets
    public function register(Request $request): JsonResponse
    {
        try {
            \Log::info('🔄 Register Request Received', $request->all());
            
            // Validasi untuk text fields
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:6',
                'email' => 'required|email|unique:users,email|unique:pelanggan,email',
                'full_name' => 'required|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'company_address' => 'nullable|string',
                'phone' => 'nullable|string|max:15',
                'id_card_number' => 'nullable|string|max:20|unique:pelanggan,no_ktp',
            ]);

            // Validasi untuk file upload
            $fileValidation = $request->validate([
                'id_card_photo' => 'required|file|mimes:jpg,jpeg,png|max:5120', // max 5MB
            ]);

            \Log::info('✅ Validation passed');

            // Start transaction
            DB::beginTransaction();

            // Handle file upload ke assets/images
            $fotoKtpPath = null;
            if ($request->hasFile('id_card_photo')) {
                $file = $request->file('id_card_photo');
                $fotoKtpPath = $this->saveToAssets($file, 'ktp_photos');
                \Log::info('📁 File stored at: ' . $fotoKtpPath);
            }

            // Create User
            $user = User::create([
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'username' => $validated['username'],
            ]);

            \Log::info('👤 User created: ' . $user->id);

            // Create Pelanggan
            $pelanggan = Pelanggan::create([
                'nama_pelanggan' => $validated['full_name'],
                'no_ktp' => $validated['id_card_number'] ?? null,
                'alamat' => $validated['company_address'] ?? null,
                'no_telp' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'foto_ktp' => $fotoKtpPath,
                'foto_profil' => null,
                'company_name' => $validated['company_name'] ?? null,
            ]);

            \Log::info('👥 Pelanggan created: ' . $pelanggan->id_pelanggan);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ],
                    'pelanggan' => [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'nama_pelanggan' => $pelanggan->nama_pelanggan,
                        'email' => $pelanggan->email,
                        'no_telp' => $pelanggan->no_telp,
                        'company_name' => $pelanggan->company_name,
                        'foto_ktp' => $this->getAssetUrl($pelanggan->foto_ktp),
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            \Log::error('❌ Validation error in register', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('❌ Error in register: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan registrasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get user profile method
    public function profile(Request $request): JsonResponse
    {
        try {
            // Untuk sementara, ambil dari header atau request
            $email = $request->input('email') ?? $request->header('X-User-Email');
            
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email tidak ditemukan',
                ], 400);
            }

            // Cari user dan pelanggan data
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 404);
            }

            $pelanggan = Pelanggan::where('email', $user->email)->first();

            return response()->json([
                'success' => true,
                'message' => 'Data profile berhasil diambil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ],
                    'pelanggan' => $pelanggan ? [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'nama_pelanggan' => $pelanggan->nama_pelanggan,
                        'email' => $pelanggan->email,
                        'no_telp' => $pelanggan->no_telp,
                        'alamat' => $pelanggan->alamat,
                        'no_ktp' => $pelanggan->no_ktp,
                        'company_name' => $pelanggan->company_name,
                        'foto_ktp' => $this->getAssetUrl($pelanggan->foto_ktp),
                        'foto_profil' => $this->getAssetUrl($pelanggan->foto_profil),
                    ] : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('❌ Error in profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update user profile method dengan foto KTP
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            \Log::info('🔄 Update Profile Request Received', $request->all());
            
            // Ambil email dari request body
            $email = $request->input('email');
            $originalEmail = $request->input('original_email');
            
            \Log::info('📧 Email identifiers:', [
                'email' => $email,
                'original_email' => $originalEmail
            ]);
            
            // Gunakan original_email untuk mencari data lama
            $searchEmail = $originalEmail ?: $email;
            
            if (!$searchEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email harus disertakan',
                ], 400);
            }

            $validated = $request->validate([
                'nama_pelanggan' => 'required|string|max:255',
                'email' => 'required|email',
                'no_telp' => 'nullable|string|max:15',
                'company_name' => 'nullable|string|max:255',
                'alamat' => 'nullable|string',
            ]);

            \Log::info('✅ Validated data for update:', $validated);

            // Cari user dan pelanggan berdasarkan email LAMA (original_email)
            $user = User::where('email', $searchEmail)->first();
            $pelanggan = Pelanggan::where('email', $searchEmail)->first();

            \Log::info('🔍 Search results:', [
                'search_email' => $searchEmail,
                'user_found' => $user ? $user->email : 'Not found',
                'pelanggan_found' => $pelanggan ? $pelanggan->email : 'Not found'
            ]);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 404);
            }

            if (!$pelanggan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pelanggan tidak ditemukan',
                ], 404);
            }

            // Handle file upload jika ada foto KTP baru
            if ($request->hasFile('foto_ktp')) {
                $fileValidation = $request->validate([
                    'foto_ktp' => 'file|mimes:jpg,jpeg,png|max:5120', // max 5MB
                ]);

                // Hapus file lama jika ada
                if ($pelanggan->foto_ktp) {
                    $this->deleteFromAssets($pelanggan->foto_ktp);
                }

                // Upload file baru ke assets
                $file = $request->file('foto_ktp');
                $fotoKtpPath = $this->saveToAssets($file, 'ktp_photos');
                $pelanggan->foto_ktp = $fotoKtpPath;
                \Log::info('📁 New KTP photo stored at: ' . $fotoKtpPath);
            }

            // Update data pelanggan
            $pelangganUpdateData = [
                'nama_pelanggan' => $validated['nama_pelanggan'],
                'email' => $validated['email'],
                'no_telp' => $validated['no_telp'] ?? $pelanggan->no_telp,
                'alamat' => $validated['alamat'] ?? $pelanggan->alamat,
                'company_name' => $validated['company_name'] ?? $pelanggan->company_name,
            ];

            \Log::info('📝 Updating pelanggan with data:', $pelangganUpdateData);
            
            $pelanggan->update($pelangganUpdateData);
            $pelanggan->refresh();

            \Log::info('✅ Pelanggan after update:', [
                'company_name' => $pelanggan->company_name,
                'nama_pelanggan' => $pelanggan->nama_pelanggan
            ]);

            // Update user data
            $userUpdateData = [
                'name' => $validated['nama_pelanggan'],
                'email' => $validated['email'],
            ];
            
            \Log::info('📝 Updating user with data:', $userUpdateData);
            $user->update($userUpdateData);
            $user->refresh();

            \Log::info('✅ User after update:', [
                'name' => $user->name,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile berhasil diperbarui',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                    ],
                    'pelanggan' => [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'nama_pelanggan' => $pelanggan->nama_pelanggan,
                        'email' => $pelanggan->email,
                        'no_telp' => $pelanggan->no_telp,
                        'alamat' => $pelanggan->alamat,
                        'company_name' => $pelanggan->company_name,
                        'foto_ktp' => $this->getAssetUrl($pelanggan->foto_ktp),
                    ]
                ]
            ], 200);

        } catch (ValidationException $e) {
            \Log::error('❌ Validation error in updateProfile', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('❌ Error in updateProfile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Method untuk upload foto KTP saja ke assets
    public function uploadKtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'id_card_photo' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            ]);

            $pelanggan = Pelanggan::where('email', $validated['email'])->first();
            
            if (!$pelanggan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pelanggan tidak ditemukan',
                ], 404);
            }

            // Hapus file lama jika ada
            if ($pelanggan->foto_ktp) {
                $this->deleteFromAssets($pelanggan->foto_ktp);
            }

            // Upload file baru ke assets
            $file = $request->file('id_card_photo');
            $fotoKtpPath = $this->saveToAssets($file, 'ktp_photos');

            $pelanggan->update([
                'foto_ktp' => $fotoKtpPath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto KTP berhasil diupload',
                'data' => [
                    'foto_ktp' => $this->getAssetUrl($fotoKtpPath)
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('❌ Error in uploadKtp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload foto KTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Your existing methods dengan modifikasi untuk assets...
    public function index(): JsonResponse
    {
        try {
            $data = Pelanggan::orderBy('id_pelanggan', 'DESC')->get();
            
            // Tambahkan URL lengkap untuk foto
            $data->transform(function ($pelanggan) {
                $pelanggan->foto_ktp_url = $this->getAssetUrl($pelanggan->foto_ktp);
                $pelanggan->foto_profil_url = $this->getAssetUrl($pelanggan->foto_profil);
                return $pelanggan;
            });
            
            return $this->successResponse($data, 'Data pelanggan berhasil diambil');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pelanggan', 500, $e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);
            
            if (!empty($validated['email'])) {
                $existingEmail = Pelanggan::where('email', $validated['email'])->first();
                if ($existingEmail) {
                    return $this->errorResponse('Email sudah digunakan', 422);
                }
            }

            if (!empty($validated['no_ktp'])) {
                $existingKtp = Pelanggan::where('no_ktp', $validated['no_ktp'])->first();
                if ($existingKtp) {
                    return $this->errorResponse('No KTP sudah digunakan', 422);
                }
            }

            // Handle file upload jika ada
            if ($request->hasFile('foto_ktp')) {
                $file = $request->file('foto_ktp');
                $validated['foto_ktp'] = $this->saveToAssets($file, 'ktp_photos');
            }

            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $validated['foto_profil'] = $this->saveToAssets($file, 'profile_photos');
            }

            $pelanggan = Pelanggan::create($validated);
            return $this->successResponse($pelanggan, 'Data pelanggan berhasil ditambahkan', 201);
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menambah data pelanggan', 500, $e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $pelanggan = Pelanggan::find($id);
            
            if (!$pelanggan) {
                return $this->errorResponse('Data pelanggan tidak ditemukan', 404);
            }

            // Tambahkan URL lengkap untuk foto
            $pelanggan->foto_ktp_url = $this->getAssetUrl($pelanggan->foto_ktp);
            $pelanggan->foto_profil_url = $this->getAssetUrl($pelanggan->foto_profil);

            return $this->successResponse($pelanggan, 'Data pelanggan berhasil diambil');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data pelanggan', 500, $e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $pelanggan = Pelanggan::find($id);
            
            if (!$pelanggan) {
                return $this->errorResponse('Data pelanggan tidak ditemukan', 404);
            }

            $validationRules = [
                'nama_pelanggan' => 'required|string|max:255',
                'no_ktp' => "nullable|string|max:20|unique:pelanggan,no_ktp,{$id},id_pelanggan",
                'alamat' => 'nullable|string',
                'no_telp' => 'nullable|string|max:15',
                'email' => "nullable|email|unique:pelanggan,email,{$id},id_pelanggan"
            ];

            $validated = $this->validateRequest($request, $validationRules);

            // Handle file upload jika ada
            if ($request->hasFile('foto_ktp')) {
                // Hapus file lama
                if ($pelanggan->foto_ktp) {
                    $this->deleteFromAssets($pelanggan->foto_ktp);
                }
                
                $file = $request->file('foto_ktp');
                $validated['foto_ktp'] = $this->saveToAssets($file, 'ktp_photos');
            }

            if ($request->hasFile('foto_profil')) {
                // Hapus file lama
                if ($pelanggan->foto_profil) {
                    $this->deleteFromAssets($pelanggan->foto_profil);
                }
                
                $file = $request->file('foto_profil');
                $validated['foto_profil'] = $this->saveToAssets($file, 'profile_photos');
            }

            $pelanggan->update($validated);

            return $this->successResponse($pelanggan, 'Data pelanggan berhasil diupdate');
            
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi gagal', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengupdate data pelanggan', 500, $e->getMessage());
        }
    }

// Method untuk upload foto profil saja
public function uploadProfilePhoto(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'email' => 'required|email',
            'foto_profil' => 'required|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $pelanggan = Pelanggan::where('email', $validated['email'])->first();
        
        if (!$pelanggan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggan tidak ditemukan',
            ], 404);
        }

        // Handle file upload ke assets/images
        $fotoProfilPath = null;
        if ($request->hasFile('foto_profil')) {
            $file = $request->file('foto_profil');
            
            // Pastikan folder assets/images/profile_photos exists
            $assetsPath = public_path('assets/images/profile_photos');
            if (!file_exists($assetsPath)) {
                mkdir($assetsPath, 0755, true);
            }
            
            // Generate unique filename
            $fileName = 'profile_' . Str::random(20) . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Simpan file ke assets/images/profile_photos
            $file->move($assetsPath, $fileName);
            
            // Simpan path relative untuk database
            $fotoProfilPath = 'assets/images/profile_photos/' . $fileName;
            
            \Log::info('📁 Profile photo saved to: ' . $fotoProfilPath);
        }

        // Hapus file lama jika ada
        if ($pelanggan->foto_profil && file_exists(public_path($pelanggan->foto_profil))) {
            unlink(public_path($pelanggan->foto_profil));
            \Log::info('🗑️ Old profile photo deleted: ' . $pelanggan->foto_profil);
        }

        // Update hanya foto_profil
        $pelanggan->update([
            'foto_profil' => $fotoProfilPath
        ]);

        // Refresh data
        $pelanggan->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diupdate',
            'data' => [
                'foto_profil' => $fotoProfilPath ? url($fotoProfilPath) : null
            ]
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('❌ Error in uploadProfilePhoto: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengupload foto profil',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function destroy($id): JsonResponse
    {
        try {
            $pelanggan = Pelanggan::find($id);
            
            if (!$pelanggan) {
                return $this->errorResponse('Data pelanggan tidak ditemukan', 404);
            }

            // Hapus file terkait dari assets
            if ($pelanggan->foto_ktp) {
                $this->deleteFromAssets($pelanggan->foto_ktp);
            }
            if ($pelanggan->foto_profil) {
                $this->deleteFromAssets($pelanggan->foto_profil);
            }

            $pelanggan->delete();
            return $this->successResponse(null, 'Data pelanggan berhasil dihapus');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus data pelanggan', 500, $e->getMessage());
        }
    }
}