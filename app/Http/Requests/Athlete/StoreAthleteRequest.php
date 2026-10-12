<?php

namespace App\Http\Requests\Athlete;

use App\Http\Requests\Athlete\Concerns\AthleteProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreAthleteRequest extends FormRequest
{
    use AthleteProfileRules;

    public function authorize(): bool
    {
        return true; // enforced in controller via Policy + AthleteService::resolveCoach
    }

    public function rules(): array
    {
        return $this->profileRules() + [
            // Required for admins, forbidden-if-foreign for coaches; checked in AthleteService.
            'coachId' => ['nullable', 'uuid'],
        ];
    }
}
