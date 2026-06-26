<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRegisterRequest;
use App\Services\AuthService;

class RegisterController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function register(AuthRegisterRequest $request)
    {
        $result = $this->authService->registerUser($request->validated());

        return response()->json($result, 201);
    }
}
