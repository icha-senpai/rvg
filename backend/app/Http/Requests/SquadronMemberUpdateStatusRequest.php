<?php

namespace App\Http\Requests;

use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use Illuminate\Foundation\Http\FormRequest;

class SquadronMemberUpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'membership_status' => 'required|string|in:' . implode(',', SquadronMembershipStatus::values()),
        ];
    }
}
