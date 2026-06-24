<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;

class CartIdentity
{
    public function __construct(
        public readonly ?User $user,
        public readonly ?string $cartUuid,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $uuid = $request->header('X-Cart-Id') ?? $request->input('cart_id');
        $normalized = is_string($uuid) && $uuid !== '' ? $uuid : null;

        return new self($request->user(), $normalized);
    }

    public function canAccess(Cart $cart): bool
    {
        if ($this->user && $cart->user_id === $this->user->id) {
            return true;
        }

        if ($this->cartUuid && $cart->uuid === $this->cartUuid) {
            return true;
        }

        return false;
    }
}
