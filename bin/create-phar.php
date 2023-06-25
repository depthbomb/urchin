<?php

$phar_name = 'urchin.phar';

try
{
    function add_directory(Phar $phar, string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        $blacklisted_files      = ['LICENSE', '.gitignore', '.phpstorm.meta.php'];
        $blacklisted_extensions = ['md', 'json'];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file)
        {
            $file_ext  = $file->getExtension();
            $file_name = $file->getBasename();
            $file_path = $file->getPathname();
            if (in_array($file_ext, $blacklisted_extensions) or in_array($file_name, $blacklisted_files))
            {
                continue;
            }

            $phar->addFile($file_path);

            echo sprintf('Added file: %s%s', $file_path, PHP_EOL);
        }
    }

    if (file_exists($phar_name))
    {
        unlink($phar_name);
    }

    $phar = new Phar($phar_name);
    $phar->startBuffering();

    add_directory($phar, 'src');
    add_directory($phar, 'vendor');

    $default_stub = $phar->createDefaultStub('src/bootstrap.php');

    $stub = '#!/usr/bin/env php'.PHP_EOL.$default_stub;

    $phar->setStub($stub);
    $phar->stopBuffering();
    $phar->compressFiles(Phar::GZ);

    echo 'PHAR successfully created!'.PHP_EOL;
    exit(0);
}
catch (Exception $e)
{
    echo $e->getMessage();
    exit(1);
}
