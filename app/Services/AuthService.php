<?php

namespace App\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Repositories\UserRepository;
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

    public function register(Request $request)
    {
       return $this->userRepository->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
    }
}