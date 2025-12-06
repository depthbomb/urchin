<?php namespace Urchin\Command;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Literal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'generate',
    description: 'Generates an asset helper class based off of Vite\'s output manifest.json',
)]
final class GenerateHelperCommand extends Command
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
        $helper_path   = self::joinPath($helper_dir, 'Assets.php');
        $manifest_dir  = realpath($input->getArgument('manifest-dir'));
        $manifest_path = self::joinPath($manifest_dir, 'manifest.json');

        if (!file_exists($manifest_path))
        {
            $io->error(sprintf('Manifest not found at expected path: %s', $manifest_path));

            return Command::FAILURE;
        }

        $io->writeln('Parsing <fg=yellow>manifest.json</> file...');

        try
        {
            /**
             * @var $preload     string[]
             * @var $assets      string[][]
             * @var $js_entries  string[]
             * @var $css_entries string[]
             */
            [$preload, $assets, $js_entries, $css_entries] = self::parse($manifest_path);

            $io->writeln(sprintf('Generating class from <fg=yellow>%d</> assets...', count($assets)));

            $class_string = $this->generateClassString($input->getOption('namespace'), $preload, $assets, $js_entries, $css_entries);

            $io->writeln(sprintf('Writing class to %s...', $helper_path));

            file_put_contents($helper_path, $class_string);

            if ($input->getOption('delete-manifest'))
            {
                $io->writeln('Deleting <fg=yellow>manifest.json</>...');
                unlink($manifest_path);
            }

            $io->success('Generated utils class from manifest!');

            return Command::SUCCESS;
        }
        catch (\Exception $e)
        {
            $io->error($e);

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
        $class->setFinal()->setReadOnly();
        $class->setComment('This class allows for retrieving of versioned assets processed from Vite.');

        $class->addConstant('GENERATED_AT', $now->format('c'))->setType('string')->setPrivate();
        $class->addConstant('PRELOAD', new Literal($this->generatePhpArray($preload)))->setType('array')->setPrivate();
        $class->addConstant('VERSIONED', new Literal($this->generatePhpArray($assets)))->setType('array')->setPrivate();
        $class->addConstant('JS', new Literal($this->generatePhpArray($js_entries)))->setType('array')->setPrivate();
        $class->addConstant('CSS', new Literal($this->generatePhpArray($css_entries)))->setType('array')->setPrivate();

        $class->addMethod('__construct')->setPrivate();

        $class->addMethod('getGeneratedDate')
            ->setFinal()
            ->setPublic()
            ->setStatic()
            ->setComment("Returns the date that this class was generated\n\n@return DateTimeImmutable")
            ->setReturnType('DateTimeImmutable')
            ->setBody('return new DateTimeImmutable(self::GENERATED_AT);');

        $class->addMethod('getPreloadAssets')
            ->setFinal()
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of public asset paths that should be preloaded\n\n@return string[]")
            ->setReturnType('array')
            ->setBody('return self::PRELOAD;');

        $class->addMethod('getVersionedAssets')
            ->setFinal()
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of all public asset paths\n\n@return string[]")
            ->setReturnType('array')
            ->setBody('return self::VERSIONED;');

        $class->addMethod('getVersionedAsset')
            ->setFinal()
            ->setPublic()
            ->setStatic()
            ->setReturnType('?string')
            ->setComment("Returns the public asset path of a file by its original name\n\n@param string \$original_name\n\n@return string|null")
            ->setBody('return self::VERSIONED[$original_name] ?? null;')
            ->addParameter('original_name')->setType('string');

        $class->addMethod('getJsEntries')
            ->setFinal()
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of public asset paths that are used as JavaScript entries\n\n@return string[]")
            ->setReturnType('array')
            ->setBody('return self::JS;');

        $class->addMethod('getCssEntries')
            ->setFinal()
            ->setPublic()
            ->setStatic()
            ->setComment("Returns an array of public asset paths that are used as CSS entries\n\n@return string[]")
            ->setReturnType('array')
            ->setBody('return self::CSS;');

        return $file;
    }

    private function generatePhpArray(array $arr): string
    {
        $php_array = var_export($arr, true);

        return preg_replace('/\s+/', '', $php_array);
    }

    /**
     * @throws \Exception
     */
    private static function parse(string $manifest_path): array
    {
        $json = file_get_contents($manifest_path);
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            throw new \Exception('manifest.json contains invalid JSON');
        }

        $preload     = [];
        $assets      = [];
        $js_entries  = [];
        $css_entries = [];

        foreach ($data as $original => $info)
        {
            $versioned = $info->file;

            if (property_exists($info, 'src'))
            {
                $assets[basename($original)] = $versioned;
            }

            if (property_exists($info, 'isEntry') and $info->isEntry)
            {
                $js_entries[] = $versioned;

                if (property_exists($info, 'css'))
                {
                    foreach ($info->css as $css_file)
                    {
                        $css_entries[] = $css_file;
                    }
                }

                if (property_exists($info, 'dynamicImports'))
                {
                    foreach ($info->dynamicImports as $preload_name)
                    {
                        $preload_file = $data->{$preload_name}->file;
                        $preload[]    = $preload_file;
                    }
                }
            }
        }

        return [$preload, $assets, $js_entries, $css_entries];
    }

    private static function joinPath(string ...$parts): string
    {
        $parts = array_filter($parts, fn ($p) => $p !== '');

        $normalized = [];
        foreach ($parts as $index => $p)
        {
            $p = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $p);

            if ($index === 0)
            {
                $p = rtrim($p, DIRECTORY_SEPARATOR);
            }
            else
            {
                $p = trim($p, DIRECTORY_SEPARATOR);
            }

            $normalized[] = $p;
        }

        return implode(DIRECTORY_SEPARATOR, $normalized);
    }
}
