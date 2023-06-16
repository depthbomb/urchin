<?php require_once __DIR__.'/../vendor/autoload.php';

use Urchin\Command\GenerateHelperCommand;
use Symfony\Component\Console\Application;
use Urchin\Command\GenerateHelperClassCommand;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

$loader = new FactoryCommandLoader([
    'generate-helper' => fn () => new GenerateHelperCommand(),
    'generate-class'  => fn () => new GenerateHelperClassCommand(),
]);

$app = new Application('urchin');
$app->setCommandLoader($loader);

exit($app->run());
