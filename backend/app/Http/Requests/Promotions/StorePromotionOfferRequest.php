<?php

namespace App\Http\Requests\Promotions;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'branch_role_id' => ['required', 'string', 'max:255'],
        ];
    }
}
