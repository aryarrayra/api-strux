public function login(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'required|string'
        ]);

        \Log::info('Login attempt for petugas:', [
            'email' => $validated['email'],
            'role' => $validated['role']
        ]);

        $petugas = Petugas::where('email', $validated['email'])->first();

        if (!$petugas) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan'
            ], 401);
        }

        // GUNAKAN CUSTOM PASSWORD CHECK (support plain text)
        if (!$petugas->checkPassword($validated['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah'
            ], 401);
        }

        if ($petugas->status !== 'Aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun petugas tidak aktif'
            ], 401);
        }

        // Hapus password dari response untuk keamanan
        $userData = $petugas->toArray();
        unset($userData['password']);

        \Log::info('Login successful for petugas ID: ' . $petugas->id_petugas);

        return response()->json([
            'success' => true,
            'data' => $userData,
            'message' => 'Login petugas berhasil'
        ]);
        
    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Error login petugas: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal login: ' . $e->getMessage()
        ], 500);
    }
}