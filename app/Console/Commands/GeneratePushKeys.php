<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePushKeys extends Command
{
    protected $signature = 'sigei:push-keys';

    protected $description = 'Genera una sola vez las claves VAPID locales sin mostrarlas ni reemplazar claves existentes';

    public function handle(): int
    {
        $path = config('sigei.push.key_file');

        if (is_file($path)) {
            $this->error('Ya existen claves Web Push. Se conservaron sin cambios.');

            return self::FAILURE;
        }

        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $opensslConfig = config('sigei.push.openssl_config');
        $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        if (! filled($opensslConfig) && PHP_OS_FAMILY === 'Windows' && is_file($bundledConfig)) {
            $opensslConfig = $bundledConfig;
        }

        if (filled($opensslConfig)) {
            $options['config'] = $opensslConfig;
        }

        $key = openssl_pkey_new($options);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            $this->error('OpenSSL no pudo generar las claves P-256. Revisa su configuración local.');

            return self::FAILURE;
        }

        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $keys = [
            'public_key' => $encode("\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
            'private_key' => $encode(str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        ];
        $handle = @fopen($path, 'x');

        if ($handle === false) {
            $this->error('No se pudo crear el archivo privado de claves. Revisa los permisos del directorio.');

            return self::FAILURE;
        }

        try {
            if (PHP_OS_FAMILY !== 'Windows') {
                chmod($path, 0600);
            }

            $data = json_encode($keys, JSON_THROW_ON_ERROR);

            if (fwrite($handle, $data) !== strlen($data)) {
                $this->error('No se pudieron guardar completamente las claves. Revisa el archivo privado antes de continuar.');

                return self::FAILURE;
            }
        } finally {
            fclose($handle);
        }

        $this->info('Claves Web Push guardadas en el archivo privado. El envío todavía no está conectado.');

        return self::SUCCESS;
    }
}
