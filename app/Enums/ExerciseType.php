<?php

namespace App\Enums;

enum ExerciseType: string
{
    case Strength = 'strength';
    case Cardio = 'cardio';
    case Stretching = 'stretching';
    case Mobility = 'mobility';
}
