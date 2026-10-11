<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$publicPath = realpath($rootPath.'/public');

if ($publicPath === false) {
    http_response_code(500);
    exit('Application public directory is unavailable.');
}

$storagePath = sys_get_temp_dir().'/laravel-storage';
$requiredDirectories = [
    $storagePath.'/app/private',
    $storagePath.'/app/public',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/cache/locks',
    $storagePath.'/framework/sessions',
    $storagePath.'/framework/views',
    $storagePath.'/logs',
];

foreach ($requiredDirectories as $directory) {
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        http_response_code(500);
        exit('Application temporary storage could not be initialized.');
    }
}

$runtimeEnvironment = [
    'LARAVEL_STORAGE_PATH' => $storagePath,
    'VIEW_COMPILED_PATH' => $storagePath.'/framework/views',
];

$certificateBase64 = getenv('AIVEN_CA_PEM_B64');
$configuredCertificate = getenv('MYSQL_ATTR_SSL_CA');
$configuredCertificatePath = is_string($configuredCertificate) && $configuredCertificate !== ''
    ? realpath($configuredCertificate)
    : false;
$bundledCertificatePath = realpath($rootPath.'/storage/certs/ca.pem');

if (
    getenv('VERCEL') !== false
    && (! is_string($certificateBase64) || $certificateBase64 === '')
    && $configuredCertificatePath === false
    && $bundledCertificatePath === false
) {
    http_response_code(500);
    exit('Database TLS certificate configuration is missing.');
}

if (is_string($certificateBase64) && $certificateBase64 !== '') {
    $certificate = base64_decode($certificateBase64, true);

    if ($certificate === false) {
        http_response_code(500);
        exit('Database TLS certificate configuration is invalid.');
    }

    $certificatePath = sys_get_temp_dir().'/aiven-ca.pem';

    if (file_put_contents($certificatePath, $certificate, LOCK_EX) === false) {
        http_response_code(500);
        exit('Database TLS certificate could not be initialized.');
    }

    chmod($certificatePath, 0600);
    $runtimeEnvironment['MYSQL_ATTR_SSL_CA'] = $certificatePath;
} elseif ($configuredCertificatePath !== false) {
    $runtimeEnvironment['MYSQL_ATTR_SSL_CA'] = $configuredCertificatePath;
} elseif ($bundledCertificatePath !== false) {
    $runtimeEnvironment['MYSQL_ATTR_SSL_CA'] = $bundledCertificatePath;
}

foreach ($runtimeEnvironment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$staticPath = realpath($publicPath.DIRECTORY_SEPARATOR.ltrim($requestPath, '/'));
$staticExtensions = [
    'css', 'js', 'mjs', 'png', 'jpg', 'jpeg', 'webp', 'svg',
    'ico', 'txt', 'woff', 'woff2', 'ttf', 'map',
];

if (
    $staticPath !== false
    && str_starts_with($staticPath, $publicPath.DIRECTORY_SEPARATOR)
    && is_file($staticPath)
    && in_array(strtolower(pathinfo($staticPath, PATHINFO_EXTENSION)), $staticExtensions, true)
) {
    return false;
}

require $publicPath.'/index.php';
