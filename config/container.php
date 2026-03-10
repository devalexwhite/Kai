<?php
declare(strict_types=1);

use App\Service\AuthService;
use App\Twig\KaiExtension;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Flash\Messages;
use Slim\Views\Twig;

return [
    'settings' => require __DIR__ . '/settings.php',

    PDO::class => function (ContainerInterface $c): PDO {
        $path   = $c->get('settings')['db']['path'];
        $dbDir  = dirname($path);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    },

    Messages::class => function (): Messages {
        return new Messages($_SESSION);
    },

    Twig::class => function (ContainerInterface $c): Twig {
        $settings = $c->get('settings')['twig'];
        $twig     = Twig::create($settings['templatePath'], [
            'cache'       => $settings['cache'],
            'auto_reload' => true,
        ]);
        $twig->addExtension(new KaiExtension());
        return $twig;
    },

    AuthService::class => \DI\autowire(),
];
