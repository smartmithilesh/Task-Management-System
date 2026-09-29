<?php

declare(strict_types=1);

use TaskManagement\Installer\InstallationManager;

require_once __DIR__.'/InstallationManager.php';

$basePath = dirname(__DIR__);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$baseUrl = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
$installUrl = $baseUrl.'/install';

if (str_ends_with($requestPath, '/requirements')) {
    header('Location: '.$installUrl, true, 302);
    exit;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('task_installer');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $baseUrl !== '' ? $baseUrl : '/',
    'secure' => (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
if (empty($_SESSION['installer_session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['installer_session_initialized'] = true;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

if (! isset($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = '';
$steps = ['requirements', 'database', 'application', 'admin'];
$labels = ['Requirements', 'Database', 'Application', 'Administrator'];
$currentStep = $_SESSION['installer_step'] ?? 'requirements';

$requirements = static function () use ($basePath): array {
    $modules = [
        'PDO' => extension_loaded('PDO'),
        'OpenSSL' => extension_loaded('openssl'),
        'Mbstring' => extension_loaded('mbstring'),
        'Fileinfo' => extension_loaded('fileinfo'),
        'Tokenizer' => extension_loaded('tokenizer'),
        'XML' => extension_loaded('xml'),
        'Ctype' => extension_loaded('ctype'),
        'JSON' => extension_loaded('json'),
        'BCMath' => extension_loaded('bcmath'),
        'GD' => extension_loaded('gd'),
        'ZIP' => extension_loaded('zip'),
        'cURL' => extension_loaded('curl'),
        'PDO MySQL driver' => extension_loaded('pdo_mysql'),
    ];
    $checks = [
        'PHP 8.3 or higher' => [PHP_VERSION_ID >= 80300, true],
    ];
    foreach ($modules as $name => $available) {
        $checks[$name] = [$available, true];
    }
    $checks['MySQL PDO driver available'] = [in_array('mysql', PDO::getAvailableDrivers(), true), true];

    foreach (['storage', 'storage/app', 'storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'] as $path) {
        $absolutePath = $basePath.'/'.$path;
        $checks[$path.' writable'] = [is_dir($absolutePath) && is_writable($absolutePath), true];
    }

    $envPath = $basePath.'/.env';
    $checks['.env writable or creatable'] = [
        (is_file($envPath) && is_writable($envPath)) || (! file_exists($envPath) && is_writable($basePath)),
        true,
    ];

    $checks['Redis extension'] = [extension_loaded('redis'), false];
    $checks['Optional: OPcache extension'] = [extension_loaded('Zend OPcache'), false];

    return $checks;
};

$render = static function (string $content, string $active, string $error = '') use ($escape, $labels, $steps): never {
    $stepIndex = array_search($active, $steps, true);
    if ($stepIndex === false) {
        $stepIndex = 0;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install Task Management System</title><style>
        :root{color-scheme:light;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f3f6fb}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.shell{width:min(100%,850px);background:#fff;border:1px solid #e4e9f2;border-radius:18px;box-shadow:0 18px 50px #1d355710;overflow:hidden}.top{padding:34px 42px 25px;border-bottom:1px solid #edf0f5}.eyebrow{font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#5869dc}.top h1{margin:9px 0 6px;font-size:26px}.top p{margin:0;color:#657089}.progress{display:flex;gap:8px;padding:20px 42px;background:#fbfcfe;border-bottom:1px solid #edf0f5}.progress span{flex:1;font-size:12px;color:#818ba0}.progress span b{display:inline-grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#e8ebf3;margin-right:7px}.progress .active{color:#3547be;font-weight:700}.progress .active b,.progress .done b{background:#4558d8;color:#fff}.body{padding:32px 42px 42px}.body h2{margin:0 0 8px;font-size:20px}.intro{margin:0 0 24px;color:#657089;line-height:1.55}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{display:grid;gap:7px;margin-bottom:17px}.field label{font-size:13px;font-weight:650}.field input,.field select{width:100%;height:44px;border:1px solid #d9dfeb;border-radius:9px;padding:0 12px;font:inherit;color:#172033;background:white}.field small{color:#768098}.requirements{border:1px solid #e6eaf1;border-radius:12px;overflow:hidden;margin-bottom:23px}.req{display:flex;justify-content:space-between;gap:16px;padding:11px 14px;border-bottom:1px solid #edf0f5;font-size:13px}.req:last-child{border-bottom:0}.pass{color:#137a51;font-weight:700}.fail{color:#b42318;font-weight:700}.optional{color:#667085;font-weight:600}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:23px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:0;border-radius:9px;padding:0 18px;background:#4356d8;color:#fff;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.button:disabled{opacity:.45;cursor:not-allowed}.alert{padding:13px 15px;border-radius:9px;background:#fff1f0;color:#a32920;margin-bottom:20px;font-size:14px}.success{padding:14px 16px;background:#edfaf3;color:#16633f;border-radius:10px;margin:18px 0}.note{font-size:13px;color:#667085;line-height:1.55}.footer{padding:15px 42px;border-top:1px solid #edf0f5;color:#858da0;font-size:12px}@media(max-width:620px){body{padding:12px}.top,.body{padding:25px 22px}.progress{padding:16px 20px;gap:3px}.progress span{font-size:0}.progress span b{font-size:12px;margin-right:0}.grid{grid-template-columns:1fr}.footer{padding:14px 22px}}
        </style></head><body><main class="shell"><header class="top"><div class="eyebrow">Task Management System</div><h1>Set up your workspace</h1><p>Complete the installation steps to configure this server.</p></header><nav class="progress" aria-label="Installation progress">';
    foreach ($steps as $index => $step) {
        $class = $index === $stepIndex ? 'active' : ($index < $stepIndex ? 'done' : '');
        echo '<span class="'.$class.'"><b>'.($index + 1).'</b>'.$escape($labels[$index]).'</span>';
    }
    echo '</nav><section class="body">';
    if ($error !== '') {
        echo '<div class="alert" role="alert">'.$escape($error).'</div>';
    }
    echo $content.'</section><footer class="footer">Installer access is protected by a one-time installation lock.</footer></main></body></html>';
    exit;
};

$field = static function (string $name, string $label, string $type = 'text', string $value = '', string $attributes = '') use ($escape): string {
    return '<div class="field"><label for="'.$escape($name).'">'.$escape($label).'</label><input id="'.$escape($name).'" name="'.$escape($name).'" type="'.$escape($type).'" value="'.$escape($value).'" '.$attributes.' required></div>';
};

$csrfInput = '<input type="hidden" name="csrf" value="'.$escape($_SESSION['installer_csrf']).'">';
$postForm = static fn (string $action, string $inside): string => '<form method="post" action="'.$escape($installUrl).'">'.$csrfInput.'<input type="hidden" name="action" value="'.$escape($action).'">'.$inside.'</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! hash_equals($_SESSION['installer_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        $render('<h2>Session expired</h2><p class="intro">Reload the installer to start a new secure session.</p>', $currentStep, 'The security token expired. Reload the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'requirements' && $currentStep === 'requirements') {
            $failed = array_filter($requirements(), static fn (array $check): bool => $check[1] && ! $check[0]);
            if ($failed !== []) {
                throw new RuntimeException('Resolve the required server checks before continuing.');
            }
            $_SESSION['installer_step'] = 'database';
            $currentStep = 'database';
        } elseif ($action === 'database' && $currentStep === 'database') {
            $host = trim((string) ($_POST['db_host'] ?? ''));
            $port = filter_var($_POST['db_port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            $databaseName = trim((string) ($_POST['db_name'] ?? ''));
            $username = trim((string) ($_POST['db_username'] ?? ''));
            $password = (string) ($_POST['db_password'] ?? '');
            $validHost = filter_var($host, FILTER_VALIDATE_IP) !== false
                || preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?))*$/', $host) === 1;
            if (! $validHost || $port === false || ! preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $databaseName) || $username === '' || strlen($username) > 128 || strlen($password) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $username) || preg_match('/[\x00-\x1F\x7F]/', $password)) {
                throw new RuntimeException('Enter a valid database host, port, name, and username.');
            }
            try {
                InstallationManager::connect(['host' => $host, 'port' => (int) $port, 'database' => $databaseName, 'username' => $username, 'password' => $password]);
            } catch (Throwable) {
                throw new RuntimeException('Unable to connect to the database. Verify the host, database name, and credentials.');
            }
            $_SESSION['installer_database'] = ['host' => $host, 'port' => (int) $port, 'database' => $databaseName, 'username' => $username, 'password' => $password];
            $_SESSION['installer_step'] = 'application';
            $currentStep = 'application';
        } elseif ($action === 'application' && $currentStep === 'application') {
            $name = trim((string) ($_POST['app_name'] ?? ''));
            $url = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
            $timezone = (string) ($_POST['timezone'] ?? '');
            $language = (string) ($_POST['language'] ?? 'en');
            $dateFormat = (string) ($_POST['date_format'] ?? 'd-m-Y');
            $timeFormat = (string) ($_POST['time_format'] ?? 'H:i');
            if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($url, PHP_URL_HOST) === null || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                throw new RuntimeException('Enter a valid application name and HTTP or HTTPS application URL.');
            }
            if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                throw new RuntimeException('Choose a valid timezone.');
            }
            if (! preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $language) || ! in_array($dateFormat, ['d-m-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y'], true) || ! in_array($timeFormat, ['H:i', 'h:i A'], true)) {
                throw new RuntimeException('Choose a supported language and date/time format.');
            }
            $_SESSION['installer_application'] = ['name' => $name, 'url' => $url, 'timezone' => $timezone, 'language' => $language, 'date_format' => $dateFormat, 'time_format' => $timeFormat];
            $_SESSION['installer_step'] = 'admin';
            $currentStep = 'admin';
        } elseif ($action === 'install' && $currentStep === 'admin') {
            $name = trim((string) ($_POST['admin_name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
            $password = (string) ($_POST['admin_password'] ?? '');
            $confirmation = (string) ($_POST['admin_password_confirmation'] ?? '');
            if ($name === '' || strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/', $name) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
                throw new RuntimeException('Enter a valid administrator name and email address.');
            }
            if (strlen($password) < 12 || strlen($password) > 256 || ! hash_equals($password, $confirmation)) {
                throw new RuntimeException('Use a password of at least 12 characters and confirm it correctly.');
            }

            $manager = new InstallationManager($basePath);
            $application = $_SESSION['installer_application'] ?? [];
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installing Task Management System</title><style>body{font:16px system-ui,sans-serif;background:#f3f6fb;color:#172033;margin:0;padding:8vh 20px}.card{max-width:680px;margin:auto;background:white;padding:36px;border:1px solid #e4e9f2;border-radius:16px}h1{font-size:25px}.muted{color:#657089}li{padding:10px 0;border-bottom:1px solid #edf0f5;color:#14784f}.error{padding:14px;background:#fff1f0;color:#a32920;border-radius:8px}.button{display:inline-block;padding:12px 16px;background:#4356d8;border-radius:8px;color:#fff;text-decoration:none;font-weight:700}</style><main class="card"><h1>Installing Task Management System</h1><p class="muted">Keep this page open while the application is configured.</p><ol>';
            flush();
            try {
                $manager->install(
                    $application,
                    $_SESSION['installer_database'] ?? [],
                    ['name' => $name, 'email' => $email, 'password' => $password],
                    static function (string $message) use ($escape): void {
                        echo '<li>✓ '.$escape($message).'</li>';
                        flush();
                    },
                );
            } catch (Throwable $exception) {
                echo '</ol><div class="error">'.$escape($exception->getMessage()).'</div><p class="muted">No installation lock was created. Return to setup to correct the issue and retry.</p><a class="button" href="'.$escape($installUrl).'">Return to installer</a></main></html>';
                exit;
            }
            $completedUrl = $escape((string) $application['url']);
            $completedEmail = $escape($email);
            session_regenerate_id(true);
            $_SESSION = [];
            echo '</ol><h2>Installation completed successfully</h2><p class="muted">The installer is now locked. Sign in with the administrator account you created.</p><p><strong>Application URL:</strong> '.$completedUrl.'</p><p><strong>Administrator email:</strong> '.$completedEmail.'</p><a class="button" href="'.$completedUrl.'">Go to application</a></main></html>';
            exit;
        } else {
            throw new RuntimeException('Complete the installer steps in order.');
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($currentStep === 'requirements') {
    $rows = '';
    $allRequired = true;
    foreach ($requirements() as $name => [$available, $required]) {
        $allRequired = $allRequired && (! $required || $available);
        $isOptional = str_starts_with($name, 'Optional:');
        $category = $isOptional ? 'Optional' : ($required ? 'Required' : 'Recommended');
        $status = $available ? '<span class="pass">✓ Ready</span>' : ($required ? '<span class="fail">✕ Required</span>' : '<span class="optional">'.$category.'</span>');
        $rows .= '<div class="req"><span>'.$escape($name).' <small>'.$category.'</small></span>'.$status.'</div>';
    }
    $disabled = $allRequired ? '' : 'disabled';
    $content = '<h2>Server requirements</h2><p class="intro">Check the server before connecting a database. Required checks must pass to continue.</p><div class="requirements">'.$rows.'</div>'.$postForm('requirements', '<div class="actions"><button class="button" type="submit" '.$disabled.'>Continue to database</button></div>');
    $render($content, $currentStep, $error);
}

if ($currentStep === 'database') {
    $db = $_SESSION['installer_database'] ?? [];
    $fields = $field('db_host', 'Database host', 'text', (string) ($db['host'] ?? 'localhost'), 'autocomplete="off"').$field('db_port', 'Database port', 'number', (string) ($db['port'] ?? '3306'), 'min="1" max="65535"').$field('db_name', 'Database name', 'text', (string) ($db['database'] ?? ''), 'autocomplete="off"').$field('db_username', 'Database username', 'text', (string) ($db['username'] ?? ''), 'autocomplete="username"').$field('db_password', 'Database password', 'password', '', 'autocomplete="new-password"');
    $render('<h2>Database configuration</h2><p class="intro">Credentials are held in the installer session and written to the private environment file after setup begins.</p>'.$postForm('database', '<div class="grid">'.$fields.'</div><div class="actions"><button class="button" type="submit">Test connection and continue</button></div>'), $currentStep, $error);
}

if ($currentStep === 'application') {
    $app = $_SESSION['installer_application'] ?? [];
    $fields = $field('app_name', 'Application name', 'text', (string) ($app['name'] ?? 'Task Management System'), 'maxlength="120"').$field('app_url', 'Application URL', 'url', (string) ($app['url'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? 'localhost'))), 'placeholder="https://example.com"').$field('timezone', 'Timezone', 'text', (string) ($app['timezone'] ?? 'UTC'), 'list="timezones" autocomplete="off"').'<datalist id="timezones"><option value="UTC"><option value="Asia/Kolkata"><option value="America/New_York"><option value="Europe/London"><option value="Australia/Sydney"></datalist>'.$field('language', 'Default language', 'text', (string) ($app['language'] ?? 'en'), 'maxlength="5"').'<div class="field"><label for="date_format">Date format</label><select id="date_format" name="date_format"><option value="d-m-Y">DD-MM-YYYY</option><option value="Y-m-d">YYYY-MM-DD</option><option value="m/d/Y">MM/DD/YYYY</option><option value="d/m/Y">DD/MM/YYYY</option></select></div><div class="field"><label for="time_format">Time format</label><select id="time_format" name="time_format"><option value="H:i">24 hour</option><option value="h:i A">12 hour</option></select></div>';
    $render('<h2>Application configuration</h2><p class="intro">Set the product name, public URL, timezone, language, and display formats.</p>'.$postForm('application', '<div class="grid">'.$fields.'</div><div class="actions"><button class="button" type="submit">Continue to administrator</button></div>'), $currentStep, $error);
}

$adminName = (string) ($_POST['admin_name'] ?? 'Administrator');
$adminEmail = (string) ($_POST['admin_email'] ?? '');
$render('<h2>Create administrator</h2><p class="intro">This account will own the initial workspace. Choose a unique email and a strong password.</p>'.$postForm('install', '<div class="grid">'.$field('admin_name', 'Name', 'text', $adminName, 'maxlength="255" autocomplete="name"').$field('admin_email', 'Email', 'email', $adminEmail, 'maxlength="255" autocomplete="email"').$field('admin_password', 'Password (12 characters minimum)', 'password', '', 'minlength="12" maxlength="256" autocomplete="new-password"').$field('admin_password_confirmation', 'Confirm password', 'password', '', 'minlength="12" maxlength="256" autocomplete="new-password"').'</div><p class="note">The installer will generate the application key, run pending migrations and seeders, and create the administrator. It will not drop existing tables.</p><div class="actions"><button class="button" type="submit">Install application</button></div>'), $currentStep, $error);
