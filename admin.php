<?php
declare(strict_types=1);

require __DIR__ . '/includes/leads.php';

ini_set('display_errors', '0');
session_name('sagi_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['csrf'];
}

function admin_is_authenticated(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

$configPath = __DIR__ . '/storage/admin-config.php';
$config = is_file($configPath) ? require $configPath : null;
if (!is_array($config) && is_file(__DIR__ . '/smtp-config.php')) {
    $smtpAdminConfig = require __DIR__ . '/smtp-config.php';
    if (is_array($smtpAdminConfig)) {
        $config = [
            'username' => $smtpAdminConfig['admin_username'] ?? '',
            'password_hash' => $smtpAdminConfig['admin_password_hash'] ?? '',
            'timezone' => $smtpAdminConfig['admin_timezone'] ?? 'Asia/Kolkata',
        ];
    }
}
$configured = is_array($config)
    && !empty($config['username'])
    && !empty($config['password_hash'])
    && password_get_info((string)$config['password_hash'])['algo'] !== null;

$timezoneName = is_array($config) ? (string)($config['timezone'] ?? 'Asia/Kolkata') : 'Asia/Kolkata';
try {
    $timezone = new DateTimeZone($timezoneName);
} catch (Throwable) {
    $timezone = new DateTimeZone('Asia/Kolkata');
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $attempts = (int)($_SESSION['login_attempts'] ?? 0);
    $lastAttempt = (int)($_SESSION['last_login_attempt'] ?? 0);

    if (!$configured) {
        $loginError = 'Admin access has not been configured yet.';
    } elseif ($attempts >= 5 && time() - $lastAttempt < 300) {
        $loginError = 'Too many attempts. Please wait five minutes and try again.';
    } else {
        $usernameOk = hash_equals((string)$config['username'], trim((string)($_POST['username'] ?? '')));
        $passwordOk = password_verify((string)($_POST['password'] ?? ''), (string)$config['password_hash']);

        if ($usernameOk && $passwordOk) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['login_attempts'] = 0;
            admin_csrf();
            header('Location: admin.php');
            exit;
        }

        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['last_login_attempt'] = time();
        $loginError = 'Incorrect username or password.';
    }
}

if (!admin_is_authenticated()):
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Admin Sign In | SAGI Realty</title>
  <link rel="icon" type="image/png" href="assets/sagi-realty-logo.png">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-brand" aria-label="SAGI Realty admin">
      <img src="assets/sagi-realty-logo-footer.png" alt="SAGI Realty">
      <p>Lead workspace</p>
      <h1>Every enquiry,<br>in one clear view.</h1>
      <span>Private access for the SAGI Realty sales team.</span>
    </section>
    <section class="login-panel">
      <div class="login-form-wrap">
        <span class="eyebrow">SECURE ADMIN</span>
        <h2>Welcome back</h2>
        <p class="login-intro">Sign in to view and manage website enquiries.</p>

        <?php if (!$configured): ?>
          <div class="notice notice-warning">
            <strong>One setup step remains</strong>
            <span>Copy <code>admin-config.example.php</code> to <code>storage/admin-config.php</code> and add your password hash.</span>
          </div>
        <?php endif; ?>

        <?php if ($loginError !== ''): ?>
          <div class="notice notice-error" role="alert"><?= h($loginError) ?></div>
        <?php endif; ?>

        <form method="post" action="admin.php" class="login-form">
          <input type="hidden" name="action" value="login">
          <label>
            <span>Username</span>
            <input type="text" name="username" autocomplete="username" required autofocus>
          </label>
          <label>
            <span>Password</span>
            <input type="password" name="password" autocomplete="current-password" required>
          </label>
          <button type="submit" <?= $configured ? '' : 'disabled' ?>>Sign in <span>&rarr;</span></button>
        </form>
        <a class="back-link" href="index.html">&larr; Return to website</a>
      </div>
    </section>
  </main>
</body>
</html>
<?php
exit;
endif;

$csrf = admin_csrf();
$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    try {
        $changed = leads_update_status((string)($_POST['id'] ?? ''), (string)($_POST['status'] ?? ''));
        $flash = $changed ? 'Lead status updated.' : 'The lead could not be updated.';
        $flashType = $changed ? 'success' : 'error';
    } catch (Throwable $exception) {
        error_log('SAGI Realty admin update error: ' . $exception->getMessage());
        $flash = 'The lead could not be updated.';
        $flashType = 'error';
    }
}

try {
    $allLeads = leads_read_all();
} catch (Throwable $exception) {
    error_log('SAGI Realty admin read error: ' . $exception->getMessage());
    $allLeads = [];
    $flash = 'Lead storage is temporarily unavailable.';
    $flashType = 'error';
}

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
$validFilters = ['all', 'new', 'contacted', 'qualified', 'closed'];
if (!in_array($statusFilter, $validFilters, true)) {
    $statusFilter = 'all';
}

$leads = array_values(array_filter($allLeads, static function (array $lead) use ($q, $statusFilter): bool {
    if ($statusFilter !== 'all' && ($lead['status'] ?? 'new') !== $statusFilter) {
        return false;
    }
    if ($q === '') {
        return true;
    }
    $haystack = implode(' ', [
        (string)($lead['name'] ?? ''),
        (string)($lead['phone'] ?? ''),
        (string)($lead['email'] ?? ''),
        (string)($lead['interest'] ?? ''),
        (string)($lead['source'] ?? ''),
    ]);
    return stripos($haystack, $q) !== false;
}));

if (isset($_GET['export'])) {
    if (!hash_equals($csrf, (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sagi-leads-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Submitted', 'Name', 'Phone', 'Email', 'Interest', 'Source', 'Status']);
    foreach ($leads as $lead) {
        $created = new DateTimeImmutable((string)$lead['created_at']);
        $row = [
            $created->setTimezone($timezone)->format('d M Y, h:i A'),
            $lead['name'] ?? '', $lead['phone'] ?? '', $lead['email'] ?? '',
            $lead['interest'] ?? '', $lead['source'] ?? '', $lead['status'] ?? 'new',
        ];
        $row = array_map(static fn ($value) => preg_match('/^[=+\-@]/', (string)$value) ? "'" . $value : $value, $row);
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

$now = new DateTimeImmutable('now', $timezone);
$today = $now->format('Y-m-d');
$counts = ['total' => count($allLeads), 'new' => 0, 'today' => 0, 'qualified' => 0];
foreach ($allLeads as $lead) {
    $leadStatus = (string)($lead['status'] ?? 'new');
    if (isset($counts[$leadStatus])) {
        $counts[$leadStatus]++;
    }
    try {
        $created = new DateTimeImmutable((string)$lead['created_at']);
        if ($created->setTimezone($timezone)->format('Y-m-d') === $today) {
            $counts['today']++;
        }
    } catch (Throwable) {
    }
}

$exportParams = array_filter(['q' => $q, 'status' => $statusFilter, 'export' => '1', 'token' => $csrf]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Leads | SAGI Realty Admin</title>
  <link rel="icon" type="image/png" href="assets/sagi-realty-logo.png">
  <link rel="stylesheet" href="css/admin.css">
  <script src="js/admin.js" defer></script>
</head>
<body class="admin-page">
  <aside class="sidebar" id="sidebar">
    <a class="admin-brand" href="admin.php"><img src="assets/sagi-realty-logo-footer.png" alt="SAGI Realty"></a>
    <nav aria-label="Admin navigation">
      <a class="active" href="admin.php"><span class="nav-icon">&#9671;</span> Leads <b><?= $counts['new'] ?></b></a>
      <a href="index.html" target="_blank"><span class="nav-icon">&#8599;</span> View website</a>
    </nav>
    <div class="sidebar-foot">
      <span>Signed in as</span>
      <strong><?= h($config['username']) ?></strong>
      <a href="admin.php?logout=1">Sign out</a>
    </div>
  </aside>

  <main class="workspace">
    <header class="workspace-header">
      <button class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false">Menu</button>
      <div>
        <span class="eyebrow">SALES WORKSPACE</span>
        <h1>Leads</h1>
        <p>Website enquiries, newest first.</p>
      </div>
      <a class="export-button" href="admin.php?<?= h(http_build_query($exportParams)) ?>">Export CSV <span>&darr;</span></a>
    </header>

    <?php if ($flash !== ''): ?>
      <div class="flash flash-<?= h($flashType) ?>" role="status"><?= h($flash) ?></div>
    <?php endif; ?>

    <section class="metrics" aria-label="Lead summary">
      <div><span>Total leads</span><strong><?= $counts['total'] ?></strong></div>
      <div><span>New</span><strong><?= $counts['new'] ?></strong></div>
      <div><span>Received today</span><strong><?= $counts['today'] ?></strong></div>
      <div><span>Qualified</span><strong><?= $counts['qualified'] ?></strong></div>
    </section>

    <section class="leads-section">
      <form class="toolbar" method="get" action="admin.php">
        <label class="search-field">
          <span class="sr-only">Search leads</span>
          <span aria-hidden="true">&#8981;</span>
          <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search name, phone or email">
        </label>
        <label class="filter-field">
          <span>Status</span>
          <select name="status">
            <?php foreach ($validFilters as $filter): ?>
              <option value="<?= h($filter) ?>" <?= $statusFilter === $filter ? 'selected' : '' ?>><?= h(ucfirst($filter)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit">Apply</button>
        <?php if ($q !== '' || $statusFilter !== 'all'): ?><a href="admin.php">Clear</a><?php endif; ?>
        <span class="result-count"><?= count($leads) ?> result<?= count($leads) === 1 ? '' : 's' ?></span>
      </form>

      <?php if (!$leads): ?>
        <div class="empty-state">
          <span>&#9671;</span>
          <h2><?= $allLeads ? 'No leads match these filters' : 'No enquiries yet' ?></h2>
          <p><?= $allLeads ? 'Try a different search or clear the filters.' : 'New form submissions will appear here automatically.' ?></p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Contact</th><th>Phone</th><th>Interest</th><th>Received</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $index => $lead):
                try {
                    $created = new DateTimeImmutable((string)$lead['created_at']);
                    $localCreated = $created->setTimezone($timezone);
                    $dateLabel = $localCreated->format('d M Y');
                    $timeLabel = $localCreated->format('h:i A');
                } catch (Throwable) {
                    $dateLabel = 'Unknown';
                    $timeLabel = '';
                }
                $leadStatus = (string)($lead['status'] ?? 'new');
            ?>
              <tr style="--row-delay: <?= min($index, 10) * 35 ?>ms">
                <td data-label="Contact">
                  <strong><?= h($lead['name'] ?? '') ?></strong>
                  <a href="mailto:<?= h($lead['email'] ?? '') ?>"><?= h($lead['email'] ?? '') ?></a>
                </td>
                <td data-label="Phone"><a class="phone-link" href="tel:+91<?= h($lead['phone'] ?? '') ?>">+91 <?= h($lead['phone'] ?? '') ?></a></td>
                <td data-label="Interest"><strong class="interest"><?= h($lead['interest'] ?? '') ?></strong><small><?= h($lead['source'] ?? '') ?></small></td>
                <td data-label="Received"><strong><?= h($dateLabel) ?></strong><small><?= h($timeLabel) ?></small></td>
                <td data-label="Status">
                  <form method="post" action="admin.php" class="status-form">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= h($lead['id'] ?? '') ?>">
                    <select name="status" class="status-select status-<?= h($leadStatus) ?>" aria-label="Status for <?= h($lead['name'] ?? 'lead') ?>">
                      <?php foreach (['new', 'contacted', 'qualified', 'closed'] as $option): ?>
                        <option value="<?= $option ?>" <?= $leadStatus === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit">Save</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <button class="sidebar-scrim" type="button" aria-label="Close menu"></button>
</body>
</html>
