<?php namespace Urchin\Util;

use Exception;

class ManifestParser
{
    /**
     * @throws Exception
     */
    public static function parse(string $manifest_path): array
    {
        $json = file_get_contents($manifest_path);
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            throw new Exception('manifest.json contains invalid JSON');
        }

        $preload     = [];
        $assets      = [];
        $js_entries  = [];
        $css_entries = [];

        foreach ($data as $original => $info)
        {
            $versioned = $info->file;
            $asset_uri = "/assets/$versioned";

            if (property_exists($info, 'src'))
            {
                $assets[basename($original)] = $asset_uri;
            }

            if (property_exists($info, 'isEntry') and $info->isEntry)
            {
                $js_entries[] = $asset_uri;

                if (property_exists($info, 'css'))
                {
                    foreach ($info->css as $css_file)
                    {
                        $css_entries[] = "/assets/$css_file";
                    }
                }

                if (property_exists($info, 'dynamicImports'))
                {
                    foreach ($info->dynamicImports as $preload_name)
                    {
                        $preload_file = $data->{$preload_name}->file;
                        $preload[]    = "/assets/$preload_file";
                    }
                }
            }
        }

        return [$preload, $assets, $js_entries, $css_entries];
    }
}
