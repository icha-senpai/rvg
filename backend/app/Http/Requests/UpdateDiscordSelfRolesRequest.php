<?php

namespace App\Http\Requests;

use App\Services\DiscordSelfRoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscordSelfRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $definitions = app(DiscordSelfRoleService::class)->roleDefinitions();
        $branchRoleIds = array_column($definitions['branches'], 'id');
        $playerRoleIds = array_column($definitions['player_roles'], 'id');

        return [
            'branch_role_ids' => ['nullable', 'array'],
            'branch_role_ids.*' => ['string', 'distinct', Rule::in($branchRoleIds)],
            'player_role_ids' => ['nullable', 'array'],
            'player_role_ids.*' => ['string', 'distinct', Rule::in($playerRoleIds)],
        ];
    }
}
