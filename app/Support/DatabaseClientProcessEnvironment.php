<?php

namespace App\Support;

use RuntimeException;

class DatabaseClientProcessEnvironment
{
    /**
     * @return array<string, string>
     */
    public function forPassword(?string $password): array
    {
        $environment = [];

        if ($password !== null && $password !== '') {
            $environment['MYSQL_PWD'] = $password;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return $environment;
        }

        foreach (['SystemRoot', 'WINDIR', 'PATH'] as $name) {
            $value = getenv($name, true);

            if (! is_string($value) || $value === '') {
                $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;
            }

            if (is_string($value) && $value !== '') {
                $environment[$name] = $value;
            }
        }

        if (! isset($environment['SystemRoot']) && ! isset($environment['WINDIR'])) {
            throw new RuntimeException('Windows no expone SystemRoot/WINDIR al proceso web; no se puede iniciar el cliente MySQL.');
        }

        return $environment;
    }
}
