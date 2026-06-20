<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Exceptions\InvalidCredentialsException;
use App\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login(Request $request): ?array
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw new InvalidCredentialsException();
        }

        $user = Auth::user();
        $token = $user->createToken($request->origin)->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function register(Request $request): array
    {
        $user = $this->userRepository->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        $emailSent = $this->sendVerificationEmail($user);

        return [
            'user' => $user,
            'email_sent' => $emailSent,
        ];
    }

    public function resendVerificationEmail(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new BaseException('User not found', 404);
        }

        if ($user->hasVerifiedEmail()) {
            throw new BaseException('Email already verified', 400);
        }

        if (! $this->sendVerificationEmail($user)) {
            throw new BaseException('Verification email could not be sent', 500);
        }
    }

    private function sendVerificationEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}