<?php namespace Urchin\Command;

use Exception;
use Urchin\Util\Fs;
use Urchin\Util\ManifestParser;
use Nette\PhpGenerator\PhpFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'generate-helper',
    description: 'Generates an asset helper file based off of Vite\'s output manifest.json',
)]
class GenerateHelperCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('helper-dir', InputArgument::REQUIRED, 'The path to the directory where the helper will be generated');
        $this->addArgument('manifest-dir', InputArgument::REQUIRED, 'The path to the directory containing <fg=yellow>manifest.json</>');
        $this->addOption('delete-manifest', 'd', InputOption::VALUE_NONE, 'Whether to delete the <fg=yellow>manifest.json</> file after generating the helper');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $helper_dir    = realpath($input->getArgument('helper-dir'));
        $helper_path   = Fs::join($helper_dir, 'assets.php');
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

            $io->writeln(sprintf('Generating helper from <fg=yellow>%d</> assets...', count($assets)));

            $php_file = $this->generateHelperFile($preload, $assets, $js_entries, $css_entries);

            $io->writeln(sprintf('Writing helper to %s...', $helper_path));

            file_put_contents($helper_path, $php_file);

            if ((bool)$input->getOption('delete-manifest'))
            {
                $io->writeln('Deleting <fg=yellow>manifest.json</>...');
                unlink($manifest_path);
            }

            $io->success('Generated helper from manifest!');

            return Command::SUCCESS;
        }
        catch (Exception $e)
        {
            $io->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function generateHelperFile(array $preload, array $assets, array $js_entries, array $css_entries): string
    {
        $now = date_create_immutable();

        $file = new PhpFile;
        $file->setStrictTypes();
        $file->addComment("This file was automatically generated on {$now->format('c')}. DO NOT modify directly.");

        $file->addFunction('get_generated_date')
            ->setComment("Returns the date that this helper was generated\n\n@return DateTimeImmutable")
            ->setReturnType('DateTimeImmutable')
            ->setBody(sprintf('return date_create_immutable(\'%s\');', $now->format('c')));

        $file->addFunction('get_preload_assets')
            ->setComment("Returns an array of public asset paths that should be preloaded\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($preload, true)));

        $file->addFunction('get_versioned_assets')
            ->setComment("Returns an array of all public asset paths\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($assets, true)));

        $file->addFunction('get_versioned_asset')
            ->setComment("Returns the public asset path of a file by its original name\n\n@param string \$original_name\n\n@return string|null")
            ->setBody('return get_versioned_assets()[$original_name];')
            ->setReturnType('?string')
            ->addParameter('original_name')->setType('string');

        $file->addFunction('get_js_entries')
            ->setComment("Returns an array of public asset paths that are used as JavaScript entries\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($js_entries, true)));

        $file->addFunction('get_css_entries')
            ->setComment("Returns an array of public asset paths that are used as CSS entries\n\n@return string[]")
            ->setReturnType('array')
            ->setBody(sprintf('return %s;', var_export($css_entries, true)));

        return $file;
    }
}
