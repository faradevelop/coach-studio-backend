<?php

namespace App\Http\Requests\Athlete\Concerns;

use App\Support\TextNormalizer;
use Illuminate\Validation\Rule;

/** Shared by Store/Update so the profile rules live in one place. */
trait AthleteProfileRules
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['firstName', 'lastName', 'phone', 'email'] as $key) {
            if (is_string($this->input($key))) {
                $normalized[$key] = TextNormalizer::normalize($this->input($key));
            }
        }

        $this->merge($normalized);
    }

    protected function profileRules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'dateOfBirth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'heightCm' => ['nullable', 'integer', 'between:50,300'],
            'weightKg' => ['nullable', 'numeric', 'between:20,500'],
            // Same values as the program goal / level enums.
            'fitnessGoal' => ['nullable', 'string', Rule::in([
                'hypertrophy', 'strength', 'fatLoss', 'endurance', 'rehabilitation',
            ])],
            'experienceLevel' => ['nullable', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'injuries' => ['nullable', 'string', 'max:5000'],
            'physicalLimitations' => ['nullable', 'string', 'max:5000'],
            'coachNotes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
