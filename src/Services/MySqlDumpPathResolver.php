<?php

namespace Karim\SmartBackup\Services;

class MySqlDumpPathResolver
{
    /** @var array<int, string> */
    private array $commonPaths = [
        'C:/xampp/mysql/bin',
        'C:/laragon/bin/mysql/mysql-8.0/bin',
        'C:/laragon/bin/mysql/mysql-8.4/bin',
        'C:/wamp64/bin/mysql/mysql8.0/bin',
        'C:/Program Files/MySQL/MySQL Server 8.0/bin',
        'C:/Program Files/MariaDB 11.0/bin',
        '/usr/bin',
        '/usr/local/bin',
        '/usr/local/mysql/bin',
        '/opt/mysql/bin',
        '/opt/mariadb/bin',
    ];

    public function resolve(?string $configuredPath = null): ?string
    {
        $configuredPath = $this->normalizePath((string) $configuredPath);

        if ($configuredPath !== '') {
            $resolvedPath = $this->resolveConfiguredPath($configuredPath);

            if ($resolvedPath !== null) {
                return $resolvedPath;
            }
        }

        $pathBinary = $this->binaryFromSystemPath();

        if ($pathBinary !== null) {
            return $this->normalizePath(dirname($pathBinary));
        }

        foreach ($this->commonPaths as $path) {
            $path = $this->normalizePath($path);

            if ($this->directoryContainsDumpBinary($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolveConfiguredPath(string $path): ?string
    {
        if ($this->isDumpBinaryPath($path)) {
            $directory = $this->normalizePath(dirname($path));

            return $this->directoryContainsDumpBinary($directory) ? $directory : null;
        }

        return $this->directoryContainsDumpBinary($path) ? $path : null;
    }

    private function binaryFromSystemPath(): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? 'where mysqldump 2>NUL'
            : 'command -v mysqldump 2>/dev/null';

        $output = $this->runCommand($command);

        if ($output === null || trim($output) === '') {
            return null;
        }

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $binary = $this->normalizePath($line);

            if ($binary !== '' && $this->isDumpBinaryPath($binary) && is_file($binary)) {
                return $binary;
            }
        }

        return null;
    }

    private function runCommand(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $disabledFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (in_array('shell_exec', $disabledFunctions, true)) {
            return null;
        }

        return @shell_exec($command);
    }

    private function directoryContainsDumpBinary(string $directory): bool
    {
        return is_file($directory . '/' . $this->binaryName());
    }

    private function isDumpBinaryPath(string $path): bool
    {
        return in_array(strtolower(basename($path)), ['mysqldump', 'mysqldump.exe'], true);
    }

    private function binaryName(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'mysqldump.exe' : 'mysqldump';
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, " \t\n\r\0\x0B\"'");
        $path = str_replace('\\', '/', $path);

        return rtrim($path, '/');
    }
}
