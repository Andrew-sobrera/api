<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\ProducerRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected ProducerRepository $producerRepository,
    ) {
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

    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data) {
            if ($data['role'] === 'producer') {
                $producer = $this->producerRepository->create([
                    'name' => $data['company_name'],
                    'cnpj' => $data['cnpj'],
                ]);

                return $this->userRepository->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => 'producer',
                    'producer_id' => $producer->id,
                ]);
            }

            return $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'user',
                'document' => $data['document'] ?? null,
            ]);
        });

        return [
            'user' => $user,
            'email_sent' => $this->sendVerificationEmail($user),
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
