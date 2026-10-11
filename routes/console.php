<?php

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Pdo\Mysql;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deployment:migrate', function () {
    $vercelEnvironment = getenv('VERCEL_ENV');

    if ($vercelEnvironment !== 'production') {
        $this->info('Skipping database migrations outside Vercel Production.');

        return Command::SUCCESS;
    }

    if (! app()->environment('production')) {
        $this->error('Refusing to migrate: APP_ENV must be production for a Vercel Production build.');

        return Command::FAILURE;
    }

    $certificatePath = null;
    $certificateBase64 = getenv('AIVEN_CA_PEM_B64');

    if (is_string($certificateBase64) && $certificateBase64 !== '') {
        $certificate = base64_decode($certificateBase64, true);

        if ($certificate === false) {
            $this->error('Aiven database TLS certificate configuration is invalid.');

            return Command::FAILURE;
        }

        $certificatePath = sys_get_temp_dir().'/aiven-ca-migrate.pem';

        if (file_put_contents($certificatePath, $certificate, LOCK_EX) === false) {
            $this->error('Aiven database TLS certificate could not be initialized.');

            return Command::FAILURE;
        }

        chmod($certificatePath, 0600);
    } else {
        $configuredCertificate = getenv('MYSQL_ATTR_SSL_CA');
        $certificateCandidates = [
            is_string($configuredCertificate) && $configuredCertificate !== '' ? $configuredCertificate : null,
            base_path('storage/certs/ca.pem'),
        ];

        foreach ($certificateCandidates as $certificateCandidate) {
            $resolvedCertificatePath = is_string($certificateCandidate)
                ? realpath($certificateCandidate)
                : false;

            if ($resolvedCertificatePath !== false && is_file($resolvedCertificatePath)) {
                $certificatePath = $resolvedCertificatePath;

                break;
            }
        }
    }

    if (! is_string($certificatePath) || ! is_file($certificatePath)) {
        $this->error('Aiven database TLS certificate configuration is missing.');

        return Command::FAILURE;
    }

    config()->set(
        'database.connections.mysql.options.'.Mysql::ATTR_SSL_CA,
        $certificatePath,
    );

    return $this->call('migrate', [
        '--force' => true,
        '--no-interaction' => true,
    ]);
})->purpose('Run pending migrations for Vercel Production deployments');
