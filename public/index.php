<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

/*
 * @param array<string, mixed> $context provided by symfony/runtime
 */
return static function (array $context): Kernel {
    $environment = $context['APP_ENV'] ?? 'prod';
    if (!is_string($environment)) {
        throw new LogicException('APP_ENV must be a string.');
    }

    return new Kernel($environment, (bool) ($context['APP_DEBUG'] ?? false));
};
