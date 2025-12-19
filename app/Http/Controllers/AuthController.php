<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    /**
     * Authenticate user and provide session-based access
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:4|max:100'
            ]);

            // Simple demo authentication (replace with proper authentication in production)
            $validCredentials = [
                'username' => 'admin',
                'password' => 'admin123'
            ];

            if ($credentials['username'] === $validCredentials['username'] &&
                $credentials['password'] === $validCredentials['password']) {

                Session::put('authenticated', true);
                Session::put('user', $credentials['username']);
                Session::put('login_time', now());

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'username' => $credentials['username'],
                        'login_time' => now()->toISOString()
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Logout user and destroy session
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            Session::forget(['authenticated', 'user', 'login_time']);
            Session::invalidate();
            Session::regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Check authentication status
     */
    public function status(Request $request): JsonResponse
    {
        $isAuthenticated = Session::has('authenticated') && Session::get('authenticated');

        if ($isAuthenticated) {
            return response()->json([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'username' => Session::get('user'),
                    'login_time' => Session::get('login_time')
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'authenticated' => false
        ]);
    }
}