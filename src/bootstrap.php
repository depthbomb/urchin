<?php require_once __DIR__.'/../vendor/autoload.php';

use Urchin\Command\GenerateHelperCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

$loader = new FactoryCommandLoader([
    'generate' => fn () => new GenerateHelperCommand(),
]);

$app = new Application('urchin');
$app->setCommandLoader($loader);

exit($app->run());
