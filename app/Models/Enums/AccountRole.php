<?php

namespace App\Models\Enums;

enum AccountRole: string
{
    case Administrator = 'Administrador';
    case Responsible = 'Responsable';
}
