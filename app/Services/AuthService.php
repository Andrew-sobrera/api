<?php

namespace App\Services;

use App\Exceptions\BaseException;
use App\Exceptions\InvalidCredentialsException;
use App\Jobs\CreateAsaasSubaccountJob;
use App\Models\ProducerPaymentMethod;
use App\Models\User;
use App\Repositories\ProducerRepository;
use App\Repositories\UserRepository;
use App\Support\QueueNames;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected ProducerRepository $producerRepository,
    ) {
    }

    public function login(array $data): array
    {
        if (! Auth::attempt([ 'email' => $data['email'], 'password' => $data['password'] ])) {
            throw new InvalidCredentialsException();
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            throw new InvalidCredentialsException();
        }

        $tokenName = $data['device_name'] ?? 'api';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function registerUser(array $data): array
    {
        $user = DB::transaction(function () use ($data) {
            return $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'] ?? 'user',
                'document' => $data['document'] ?? null,
            ]);
        });

        $token = $user->createToken($data['device_name'] ?? 'api')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function loginWithGoogle(array $payload): array
    {
        $user = $this->userRepository->createIfNotExistsByGoogle($payload);

        $token = $user->createToken('google')->plainTextToken;

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
                    'fantasy_name' => $data['fantasy_name'] ?? null,
                    'cnpj' => $data['cnpj'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'],
                    'address' => $data['address'] ?? null,
                ]);

                // Cria métodos de pagamento padrão (PIX ativo)
                ProducerPaymentMethod::create([
                    'producer_id' => $producer->id,
                    'payment_method' => 'PIX',
                    'max_installments' => 1,
                    'active' => true,
                ]);

                $user = $this->userRepository->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => 'producer',
                    'producer_id' => $producer->id,
                    'document' => $data['cnpj'],
                ]);

                // Dispara criação da subconta de forma assíncrona
                CreateAsaasSubaccountJob::dispatch($producer->id)
                    ->onConnection(config('queue.default'))
                    ->onQueue(QueueNames::ASAAS_SUBACCOUNTS);

                return $user;
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
