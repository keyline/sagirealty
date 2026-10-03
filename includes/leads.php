<?php
declare(strict_types=1);

/**
 * Small file-backed lead repository.
 *
 * The data file starts with a PHP exit statement, so it cannot be downloaded
 * as plain JSON even when the project root is the web root.
 */

const LEADS_STORAGE_HEADER = "<?php http_response_code(404); exit; ?>\n";

function leads_storage_directory(): string
{
    $override = getenv('SAGI_LEADS_STORAGE_DIR');
    if (is_string($override) && $override !== '') {
        return rtrim($override, '\\/');
    }
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
}

function leads_storage_path(): string
{
    return leads_storage_directory() . DIRECTORY_SEPARATOR . 'leads.php';
}

function leads_lock_path(): string
{
    return leads_storage_directory() . DIRECTORY_SEPARATOR . 'leads.lock';
}

function leads_prepare_storage(): void
{
    $directory = leads_storage_directory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create lead storage directory.');
    }

    $denyFile = $directory . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($denyFile)) {
        @file_put_contents($denyFile, "Require all denied\n", LOCK_EX);
    }
}

/** @return array<int, array<string, mixed>> */
function leads_decode(string $contents): array
{
    if (str_starts_with($contents, LEADS_STORAGE_HEADER)) {
        $contents = substr($contents, strlen(LEADS_STORAGE_HEADER));
    }

    if (trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? array_values($decoded) : [];
}

/** @return array<int, array<string, mixed>> */
function leads_read_all(): array
{
    leads_prepare_storage();
    $lock = fopen(leads_lock_path(), 'c+');
    if ($lock === false) {
        throw new RuntimeException('Unable to open lead storage lock.');
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to lock lead storage.');
        }

        $path = leads_storage_path();
        $contents = is_file($path) ? file_get_contents($path) : '';
        return leads_decode($contents === false ? '' : $contents);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** @param array<int, array<string, mixed>> $leads */
function leads_write_unlocked(array $leads): void
{
    $payload = LEADS_STORAGE_HEADER . json_encode(
        array_values($leads),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $path = leads_storage_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open lead storage.');
    }

    try {
        if (!ftruncate($handle, 0) || rewind($handle) === false || fwrite($handle, $payload) === false) {
            throw new RuntimeException('Unable to write lead storage.');
        }
        fflush($handle);
    } finally {
        fclose($handle);
    }
}

/** @param array<string, string> $lead */
function leads_add(array $lead): array
{
    leads_prepare_storage();
    $lock = fopen(leads_lock_path(), 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock lead storage.');
    }

    try {
        $path = leads_storage_path();
        $contents = is_file($path) ? file_get_contents($path) : '';
        $leads = leads_decode($contents === false ? '' : $contents);

        $record = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $lead['name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
            'interest' => $lead['interest'],
            'source' => $lead['source'],
            'status' => 'new',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];

        array_unshift($leads, $record);
        leads_write_unlocked($leads);
        return $record;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function leads_update_status(string $id, string $status): bool
{
    $allowed = ['new', 'contacted', 'qualified', 'closed'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    leads_prepare_storage();
    $lock = fopen(leads_lock_path(), 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to lock lead storage.');
    }

    try {
        $path = leads_storage_path();
        $contents = is_file($path) ? file_get_contents($path) : '';
        $leads = leads_decode($contents === false ? '' : $contents);
        $updated = false;

        foreach ($leads as &$lead) {
            if (($lead['id'] ?? '') === $id) {
                $lead['status'] = $status;
                $lead['updated_at'] = gmdate('c');
                $updated = true;
                break;
            }
        }
        unset($lead);

        if ($updated) {
            leads_write_unlocked($leads);
        }
        return $updated;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
