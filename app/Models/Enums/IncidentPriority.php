<?php

namespace App\Models\Enums;

enum IncidentPriority: string
{
    case Unevaluated = 'Por evaluar';
    case High = 'Alta';
    case Medium = 'Media';
    case Low = 'Baja';
}
