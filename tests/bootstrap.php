<?php declare(strict_types=1);

$autoloaders = [
    \dirname(__DIR__) . '/vendor/autoload.php',
    \dirname(__DIR__, 4) . '/vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require_once $autoloader;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Frosh\\Jetpack\\Tests\\' => __DIR__ . '/',
        'Frosh\\Jetpack\\' => \dirname(__DIR__) . '/src/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $directory . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }

        return;
    }
});
