<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->method->name,
            'person_name' => $this->when($this->person_name !== null, $this->person_name),
            'email' => $this->when($this->email !== null, $this->email),
            'ci' => $this->when($this->ci !== null, $this->ci),
            'phone_number' => $this->when($this->phone_number !== null, $this->phone_number),
            'bank' => $this->when($this->bank !== null, $this->bank),
            'account_number' => $this->when($this->account_number !== null, $this->account_number),
            'username' => $this->when($this->username !== null, $this->username),
            'email' => $this->when($this->email !== null, $this->email),
            'comision' => $this->when($this->comision !== null, $this->comision),
            'cash_currency' => $this->when($this->cash_currency !== null, $this->cash_currency),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
