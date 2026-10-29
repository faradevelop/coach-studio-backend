<?php

namespace App\Enums;

enum Equipment: string
{
    case Barbell = 'barbell';
    case Dumbbell = 'dumbbell';
    case Machine = 'machine';
    case Cable = 'cable';
    case Bodyweight = 'bodyweight';
    case Other = 'other';
}
