<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'order_id' => $this->id,
            'payment_method' => $this->payment_method->value,
            'status' => $this->payment_status->value,
        ];

        if ($this->payment_method === PaymentMethod::PIX) {
            $data['pix_payload'] = $this->pix_payload;
            $data['pix_qr_code_url'] = $this->pix_qr_code_url;
        }

        return $data;
    }
}
