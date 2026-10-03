<?php
declare(strict_types=1);

$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sagi-leads-' . bin2hex(random_bytes(5));
putenv('SAGI_LEADS_STORAGE_DIR=' . $testDirectory);

require dirname(__DIR__) . '/includes/leads.php';

$lead = leads_add([
    'name' => 'Test Lead',
    'phone' => '9876543210',
    'email' => 'lead@example.com',
    'interest' => '3 BHK',
    'source' => 'Smoke test',
]);

$stored = leads_read_all();
if (count($stored) !== 1 || $stored[0]['id'] !== $lead['id']) {
    throw new RuntimeException('Lead was not stored correctly.');
}

if (!leads_update_status($lead['id'], 'qualified')) {
    throw new RuntimeException('Lead status was not updated.');
}

$updated = leads_read_all();
if (($updated[0]['status'] ?? '') !== 'qualified') {
    throw new RuntimeException('Updated lead status was not persisted.');
}

foreach (glob($testDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
    unlink($file);
}
$denyFile = $testDirectory . DIRECTORY_SEPARATOR . '.htaccess';
if (is_file($denyFile)) {
    unlink($denyFile);
}
rmdir($testDirectory);

echo "Lead storage smoke test passed.\n";
