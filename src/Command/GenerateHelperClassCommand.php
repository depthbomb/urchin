<?php namespace Urchin\Command;

use Exception;
use Urchin\Util\Fs;
use Urchin\Util\ManifestParser;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'generate-class',
    description: 'Generates an asset helper class based off of Vite\'s output manifest.json',
)]
class GenerateHelperClassCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('helper-dir', InputArgument::REQUIRED, 'The path to the directory where the helper will be generated');
        $this->addArgument('manifest-dir', InputArgument::REQUIRED, 'The path to the directory containing <fg=yellow>manifest.json</>');
        $this->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'The namespace for the generated helper');
        $this->addOption('delete-manifest', 'd', InputOption::VALUE_NONE, 'Whether to delete the <fg=yellow>manifest.json</> file after generating the helper');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $helper_dir    = realpath($input->getArgument('helper-dir'));
        $helper_path   = Fs::join($helper_dir, 'Assets.php');
        $manifest_dir  = realpath($input->getArgument('manifest-dir'));
        $manifest_path = Fs::join($manifest_dir, 'manifest.json');

        if (!file_exists($manifest_path))
        {
            $io->error(sprintf('Manifest not found at expected path: %s', $manifest_path));

            return Command::FAILURE;
        }

        $io->writeln('Parsing <fg=yellow>manifest.json</> file...');

        /** @var string[] $preload */
        $preload = [];
        /** @var string[] $assets */
        $assets = [];
        /** @var string[] $js_entries */
        $js_entries = [];
        /** @var string[] $css_entries */
        $css_entries = [];

        try
        {
            ManifestParser::parse($manifest_path, $preload, $assets, $js_entries, $css_entries);

            $io->writeln(sprintf('Generating class from <fg=yellow>%d</> assets...', count($assets)));

            $class_string = $this->generateClassString($input->getOption('namespace'), $preload, $assets, $js_entries, $css_entries);

            $io->writeln(sprintf('Writing class to %s...', $helper_path));

            file_put_contents($helper_path, $class_string);

            if ((bool)$input->getOption('delete-manifest'))
            {
                $io->writeln('Deleting <fg=yellow>manifest.json</>...');
                unlink($manifest_path);
            }

            $io->success('Generated utils class from manifest!');

            return Command::SUCCESS;
        }
        catch (Exception $e)
        {
            $io->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function generateClassString(string $class_namespace, array $preload, array $assets, array $js_entries, array $css_entries): string
    {
        $now = date_create_immutable();

        $file = new PhpFile;
        $file->setStrictTypes();

        $file->addComment("This file was automatically generated on {$now->format('c')}. DO NOT modify directly.");
        $namespace = $file->addNamespace($class_namespace);
        $namespace->addUse('DateTimeImmutable');

        $class = $namespace->addClass('Assets');
        $class->setComment("This class allows for retrieving of versioned assets processed from Vite.")->setFinal();
        $class->addMethod('getGeneratedDate')
            ->setPublic()
            ->setStatic()
            ->setComment("Returns the date that this class was generated\n\n@return DateTimeImmutable")
            ->setReturnType('DateTimeImmutable')
            ->setBody(sprintf('return date_create_immutable(\'%s\');', $now->format('c')))
            ->setFinal();

        $class->addMethod('getPreloadAssets')
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of public asset paths that should be preloaded\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($preload, true)))
            ->setFinal();

        $class->addMethod('getVersionedAssets')
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of all public asset paths\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($assets, true)))
            ->setFinal();

        $class->addMethod('getVersionedAsset')
            ->setPublic()
            ->setStatic()
            ->setComment("Returns the public asset path of a file by its original name\n\n@param string \$original_name\n\n@return string|null")
            ->setBody('return self::getVersionedAssets()[$original_name];')
            ->setFinal()
            ->setReturnType('?string')
            ->addParameter('original_name')->setType('string');

        $class->addMethod('getJsEntries')
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of public asset paths that are used as JavaScript entries\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($js_entries, true)))
            ->setFinal();

        $class->addMethod('getCssEntries')
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of public asset paths that are used as CSS entries\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($css_entries, true)))
            ->setFinal();

        return $file;
    }
}
