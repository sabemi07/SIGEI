<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final class IncidentFolio
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function nextShort(ConnectionInterface $connection): string
    {
        for ($attempt = 0; $attempt < 64; $attempt++) {
            $code = self::randomCode();
            $folio = 'SIG-'.$code;

            if (! $connection->table('incidents')->where('folio_corto', $folio)->exists()) {
                return $folio;
            }
        }

        throw new RuntimeException('No se pudo asignar un folio corto único.');
    }

    private static function randomCode(): string
    {
        do {
            $code = '';
            for ($position = 0; $position < 6; $position++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (! preg_match('/[0-9]/', $code) || ! preg_match('/[A-Z]/', $code));

        return $code;
    }
}
