<?php namespace Urchin\Util;

class Fs
{
    public static function joinPath(string ...$paths): string
    {
        $paths = array_map(fn (string $path) => rtrim(str_replace(["\\", "/"], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR), $paths);
        return implode(DIRECTORY_SEPARATOR, $paths);
    }
}
