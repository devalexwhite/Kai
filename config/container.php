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
        $db  = $c->get('settings')['db'];
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);

        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
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
