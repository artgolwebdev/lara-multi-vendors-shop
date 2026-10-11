<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password', 'phone', 'address']));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        $request->session()->regenerate();

        return (new UserResource($request->user()))->response();
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->json(status: 204);
    }

    public function user(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))->response()->setStatusCode(200);
    }
}
