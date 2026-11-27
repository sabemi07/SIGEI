<?php

namespace App\Models\Enums;

enum IncidentStatus: string
{
    case Pending = 'Pendiente';
    case Received = 'Recibida';
    case InProgress = 'En atención';
    case Attended = 'Atendida';
    case Closed = 'Cerrada';
}
