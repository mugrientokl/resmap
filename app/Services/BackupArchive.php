<?php

namespace App\Services;

use JsonException;
use RuntimeException;
use ZipArchive;

class BackupArchive
{
    /**
     * @return array{
     *     format_version: int,
     *     created_at: string,
     *     driver: string,
     *     database_file: string,
     *     files: array<string, array{sha256: string, size: int}>
     * }
     *
     * @throws JsonException
     */
    public function verify(string $archivePath, ?string $password = null): array
    {
        $archive = new ZipArchive;
        $opened = $archive->open($archivePath, ZipArchive::CHECKCONS);

        if ($opened !== true) {
            throw new RuntimeException('El archivo no es un ZIP válido o está dañado.');
        }

        try {
            if ($password !== null && $password !== '') {
                $archive->setPassword($password);
            }

            $manifestContents = $archive->getFromName('manifest.json');

            if (! is_string($manifestContents)) {
                throw new RuntimeException('El backup no contiene un manifiesto legible.');
            }

            $manifest = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($manifest)
                || ($manifest['format_version'] ?? null) !== 1
                || ! in_array($manifest['driver'] ?? null, ['sqlite', 'mysql', 'mariadb'], true)
                || ! is_string($manifest['database_file'] ?? null)
                || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('El manifiesto del backup tiene un formato no válido.');
            }

            $expectedDatabaseFile = $manifest['driver'] === 'sqlite' ? 'database.sqlite' : 'database.sql';

            if ($manifest['database_file'] !== $expectedDatabaseFile
                || ! array_key_exists($expectedDatabaseFile, $manifest['files'])) {
                throw new RuntimeException('El backup no contiene la copia de base de datos esperada.');
            }

            foreach ($manifest['files'] as $name => $metadata) {
                if (! is_string($name)
                    || ! $this->isAllowedEntry($name, $expectedDatabaseFile)
                    || ! is_array($metadata)
                    || ! is_string($metadata['sha256'] ?? null)
                    || ! preg_match('/^[a-f0-9]{64}$/', $metadata['sha256'])
                    || ! is_int($metadata['size'] ?? null)) {
                    throw new RuntimeException('El manifiesto contiene archivos o checksums no válidos.');
                }

                $stream = $archive->getStream($name);

                if (! is_resource($stream)) {
                    throw new RuntimeException('No se puede leer un archivo del backup: '.$name);
                }

                $context = hash_init('sha256');
                $size = hash_update_stream($context, $stream);
                fclose($stream);
                $checksum = hash_final($context);

                if ($size !== $metadata['size'] || ! hash_equals($metadata['sha256'], $checksum)) {
                    throw new RuntimeException('No coincide la integridad del archivo: '.$name);
                }
            }

            $expectedEntries = array_keys($manifest['files']);
            $expectedEntries[] = 'manifest.json';
            $actualEntries = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);

                if (! is_string($name)) {
                    throw new RuntimeException('No se pudo leer el índice de archivos del backup.');
                }

                $actualEntries[] = $name;
            }

            sort($expectedEntries);
            sort($actualEntries);

            if ($expectedEntries !== $actualEntries) {
                throw new RuntimeException('El contenido del backup no coincide con su manifiesto.');
            }

            return $manifest;
        } finally {
            $archive->close();
        }
    }

    private function isAllowedEntry(string $name, string $databaseFile): bool
    {
        if ($name === $databaseFile) {
            return true;
        }

        if (! str_starts_with($name, 'uploads/')) {
            return false;
        }

        $relativePath = substr($name, strlen('uploads/'));

        if ($relativePath === '' || str_contains($relativePath, '\\') || str_contains($relativePath, "\0")) {
            return false;
        }

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
