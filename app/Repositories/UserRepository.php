<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function createIfNotExistsByGoogle(array $payload)
    {
        $user = $this->model->where('email', $payload['email'])->first();

        if ($user) {
            $user->fill([
                'google_id' => $user->google_id ?: $payload['google_id'],
                'avatar' => $payload['avatar'] ?? $user->avatar,
                'provider' => 'google',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $user;
        }

        return $this->model->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'google_id' => $payload['google_id'],
            'avatar' => $payload['avatar'] ?? null,
            'provider' => 'google',
            'email_verified_at' => now(),
            'password' => null,
        ]);
    }
}