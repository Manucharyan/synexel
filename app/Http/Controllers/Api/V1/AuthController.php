<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AuthHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function createToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'token_name' => ['nullable', 'string', 'max:255'],
        ]);

        $login = $data['username'] ?? $data['email'] ?? null;

        if (! $login) {
            return response()->json(['message' => 'Provide email or username.'], 422);
        }

        if (! AuthHelper::attempt($login, $data['password'])) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = $request->user();
        $token = $user->createToken($data['token_name'] ?? 'api-token');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function revokeTokens(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'All tokens revoked.']);
    }
}
