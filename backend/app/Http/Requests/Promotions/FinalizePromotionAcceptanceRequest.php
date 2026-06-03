<?php

namespace App\Http\Requests\Promotions;

use Illuminate\Foundation\Http\FormRequest;

class FinalizePromotionAcceptanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discord_id' => ['required', 'string', 'max:255'],
            'message_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
