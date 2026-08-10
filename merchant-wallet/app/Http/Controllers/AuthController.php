<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Tymon\JWTAuth\JWTGuard;

class AuthController extends Controller implements HasMiddleware
{
    protected string $guard;

    public function __construct(string $guard = 'api')
    {
        $this->guard = $guard;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', except: ['login', 'register']),
        ];
    }

    /**
     * Helper to get the type-hinted JWT Auth Guard.
     */
    protected function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return auth($this->guard);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = $this->guard()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Self-service registration: create a user + wallet and issue a token.
     *
     * @return JsonResponse
     */
    public function register()
    {
        $data = app(RegisterRequest::class)->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => $data['password'],
        ]);

        app(WalletService::class)->getOrCreateWallet($user->id);

        // Self-registration is player-only; grant the end-user role.
        app(AccessControlService::class)->assign($user, RoleName::EndUser);

        return $this->respondWithToken($this->guard()->login($user));
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me()
    {
        return response()->json($this->guard()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }
}
