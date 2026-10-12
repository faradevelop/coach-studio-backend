<?php

namespace App\Http\Requests\Athlete;

use App\Http\Requests\Athlete\Concerns\AthleteProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAthleteRequest extends FormRequest
{
    use AthleteProfileRules;

    public function authorize(): bool
    {
        return true; // ownership enforced in controller (scoped query + Policy)
    }

    // The owning coach cannot be changed here (coach transfer is out of scope).
    public function rules(): array
    {
        return $this->profileRules();
    }
}
