<?php
declare(strict_types=1);

return [
    App\Domain\Conta\ContaRepositorio::class                => App\Infrastructure\Persistencia\ContaRepositorioMySQL::class,
    App\Domain\Saque\SaqueRepositorio::class                => App\Infrastructure\Persistencia\SaqueRepositorioMySQL::class,
    App\Domain\Saque\NotificacaoSaque::class                => App\Infrastructure\Email\NotificacaoSaqueEmail::class,
    App\Application\GerenciadorDeTransacao::class           => App\Infrastructure\Persistencia\GerenciadorDeTransacaoMySQL::class,
    Hyperf\Crontab\Strategy\StrategyInterface::class        => Hyperf\Crontab\Strategy\CoroutineStrategy::class,
];
