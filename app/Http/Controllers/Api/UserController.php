<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserController extends Controller
{
    public function getProfile()
    {
        try {
            $user = Auth::user();

            return response()->json([
                'id' => $user->id,
                'fullname' => $user->fullname,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting profile: ' . $e->getMessage());

            return response()->json([
                'error' => 'Gagal mengambil profil',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $request->validate([
                'fullname' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . Auth::id(),
            ]);

            $userId = Auth::id();


            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'fullname' => $request->fullname,
                    'email' => $request->email,
                    'updated_at' => now(),
                ]);


            $user = User::find($userId);

            return response()->json([
                'message' => 'Profil berhasil diupdate',
                'data' => [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());

            return response()->json([
                'error' => 'Gagal mengupdate profil',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
