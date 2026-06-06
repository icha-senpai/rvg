<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User is already behind auth:sanctum, so allow here
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'region' => ['nullable', 'string', 'in:EU,US,APAC'],

            'favorite_ships' => ['nullable', 'array'],
            'favorite_ships.*' => ['string', 'max:80'],

            'favorite_guns' => ['nullable', 'array'],
            'favorite_guns.*' => ['string', 'max:80'],

            'primary_role' => ['nullable', 'string', 'max:64'],
            'secondary_role' => ['nullable', 'string', 'max:64'],

            'experience_ratings' => ['nullable', 'array'],
            'experience_ratings.space_combat' => ['nullable', 'integer', 'min:1', 'max:5'],
            'experience_ratings.ground_combat' => ['nullable', 'integer', 'min:1', 'max:5'],
            'experience_ratings.logistics_support' => ['nullable', 'integer', 'min:1', 'max:5'],
            'experience_ratings.medical' => ['nullable', 'integer', 'min:1', 'max:5'],

            'preferred_gameplay_style' => ['nullable', 'string', 'max:64'],

            'callsign' => ['nullable', 'string', 'max:64'],

            'typical_op_commitment' => ['nullable', 'string', 'max:16', 'in:full,partial,flexible'],

            'preferred_roles' => ['nullable', 'array'],
            'preferred_roles.*' => ['string', 'max:50'],

            'notification_settings' => ['nullable', 'array'],
            'notification_settings.*'   => ['boolean'],

            'availability_status' => ['nullable', 'string', 'max:32'],
            'loa_note' => ['nullable', 'string', 'max:1000'],
            'site_theme' => ['nullable', 'string', 'in:horizon,dark,aegis,anvil,argo,crusader,drake,origin,misc,mirai,rsi,consolidated_outland,aopoa,banu,esperia,gatac,kruger,greycat,tumbril'],

            'personal_tags' => ['nullable', 'array'],
            'personal_tags.*' => ['string', 'max:50'],
        ];
    }
}
