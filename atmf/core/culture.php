<?php

/**
 * ATMF culture handler. Part of ATMF core.
 * @version: ATMF-PHP Engine 1.1
 * @license: Apache-2.0 License
 * @repository: https://github.com/skito/ATMF-PHP
 */

namespace ATMF;

class Culture
{
    static public $settings = null;
    static private $_cachedTranslations = [];
    static private $_aliases = [];
    static private $_loadedResources = [];

    public static function ProcessTag($sender, $tagName, $args)
    {
        $keypath = substr($tagName, 1);
        $realKeypath = $keypath;

        if (!isset(self::$_cachedTranslations[$keypath]))
        {
            $hasAnAlias = false;
            foreach(self::$_aliases as $alias => $path)
            {
                if (strpos($keypath, '.') !== false || $alias != $path)
                {
                    $parts = explode('.', $keypath);
                    if ($parts[0] == $alias)
                    {
                        unset($parts[0]);
                        $realKeypath = $path.'.'.implode('.', $parts);
                        $hasAnAlias = true;
                    }
                }
                elseif (isset(self::$_cachedTranslations[$path.'.'.$keypath]))
                {
                    $realKeypath = $path.'.'.$keypath;
                    $hasAnAlias = true;
                }

                if ($hasAnAlias) break;
            }

            if (!$hasAnAlias)
                self::LoadResource($sender, $keypath);
        }

        if (!isset(self::$_cachedTranslations[$realKeypath])) return null;

        $resource = self::$_cachedTranslations[$realKeypath];
        $translation = null;
        if (is_array($resource))
        {
            $plural = isset($args[0]) && is_numeric($args[0]) && $args[0] != 1;
            $translation = !$plural && isset($resource[1]) ? $resource[1] : ($resource[0] ?? null);
        }
        else $translation = $resource;

        if (!empty($args))
        {
            // strtr() replaces $10 before $1 and never touches the inserted values
            $placeholders = [];
            foreach($args as $key => $arg)
            {
                $placeholders['$'.$key] = Tag::ToString($arg);
            }
            $translation = strtr(Tag::ToString($translation), $placeholders);
        }

        return $translation;
    }

    public static function SetTag($sender, $tagName, $args, $value)
    {
        $keypath = substr($tagName, 1);
        self::$_cachedTranslations[$keypath] = $value;
        return true;
    }

    public static function AddAlias($sender, $alias, $path)
    {
        self::$_aliases[$alias] = $path;
        self::LoadResource($sender, $path);
    }

    public static function ResetTranslations()
    {
        self::$_cachedTranslations = [];
        self::$_aliases = [];
        self::$_loadedResources = [];
    }

    /**
     * Load the resource file of a key once, even if the file or the key doesn't exist
     * @param mixed $sender ATMF engine
     * @param string $keyname Resource key, e.g. page.title
     */
    private static function LoadResource($sender, $keyname)
    {
        $resourcePath = self::ResourcePath($keyname);
        if (isset(self::$_loadedResources[$resourcePath])) return;

        self::$_loadedResources[$resourcePath] = true;

        // Translations already in place (e.g. set with __('@page.title', ...)) are kept
        self::$_cachedTranslations += self::ResolveCultureResource($sender, $keyname);
    }

    /**
     * Get resource file path of a key, e.g. header/master for header/master.title
     * @param string $keyname Resource key
     * @return string Path relative to the culture folder, without extension
     */
    private static function ResourcePath($keyname)
    {
        $keypath = '';
        $divider = '';
        foreach(explode('/', $keyname) as $namespace)
        {
            $keypath .= $divider.explode('.', $namespace)[0];
            $divider = '/';
        }

        return $keypath;
    }

    public static function ResolveCultureResource($sender, $keyname, $locale=null)
    {

        self::LoadSettings($sender);

        $cultureResources = [];
        $localePath = (string)($locale ?? $sender->GetCulture());

        $localeData = self::$settings['locale'] ?? [];
        $default = self::$settings['default'] ?? '';
        $defaultLang = $localeData[$default] ?? [];
        // Without locale settings (culture.json), the culture folder is used as it is
        $langCode = isset($localeData[$localePath]) || empty($localeData) ? $localePath : $default;
        $lang = $localeData[$localePath] ?? $defaultLang;
        if (isset($lang['extend']) && isset($localeData[$lang['extend']]))
            $cultureResources = self::ResolveCultureResource($sender, $keyname, $lang['extend']);

        $keypath = self::ResourcePath($keyname);
        $translations = self::LoadJson($sender->GetCultureFolder().'/'.$langCode.'/'.$keypath) ?? [];

        foreach($translations as $key => $value)
        {
            $cultureResources[$keypath.'.'.$key] = $value;
        }

        return $cultureResources;
    }

    /**
     * Load JSON file, through its PHP cache
     * @param string $path File path without extension
     * @return array|null NULL if the file doesn't exist
     */
    private static function LoadJson($path)
    {
        $jsonFile = $path.'.json';
        $cacheFile = $path.'.cache.php';
        $hasJson = is_file($jsonFile);

        if (is_file($cacheFile) && (!$hasJson || filemtime($cacheFile) >= filemtime($jsonFile)))
        {
            $data = include($cacheFile);
            if (is_array($data)) return $data;
        }

        return $hasJson ? self::TranslationCache($path) : null;
    }

    private static function TranslationCache($path)
    {
        $translations = json_decode(file_get_contents($path.'.json'));
        if (!is_array($translations) && !is_object($translations)) return [];

        $cacheFile = $path.'.cache.php';
        $phpCache = '<?php'."\n".'return '.trim(self::Export($translations), ", \n").";";

        // Write through a temporary file, so concurrent requests never include a partial cache
        $tmpFile = $cacheFile.'.'.uniqid('', true).'.tmp';
        if (@file_put_contents($tmpFile, $phpCache) !== false && @rename($tmpFile, $cacheFile))
        {
            if (function_exists('opcache_invalidate')) @opcache_invalidate($cacheFile, true);
        }
        else @unlink($tmpFile);

        // Same values as the cache would return, even if it can't be written
        return self::Normalize($translations);
    }

    private static function Export($value, $level=0) {
        $phpCache = '';
        $prefix = str_repeat("\t", $level);
        if (is_array($value) || is_object($value)) {
            $phpCache .= "[\n";
            foreach($value as $k => $v) {
                $phpCache .= $prefix;
                $phpCache .= is_object($value) ? var_export((string)$k, true)." => ".self::Export($v, $level + 1) : self::Export($v, $level + 1);
            }
            $phpCache .= $prefix."],\n";
        }
        else $phpCache .= var_export((string)$value, true).",\n";

        return $phpCache;
    }

    private static function Normalize($value) {
        if (!is_array($value) && !is_object($value))
            return (string)$value;

        $result = [];
        foreach($value as $k => $v) {
            $result[$k] = self::Normalize($v);
        }

        return $result;
    }

    public static function LoadSettings($sender)
    {
        if (self::$settings !== null)
            return;

        self::$settings = self::LoadJson($sender->GetCultureFolder() . '/culture');
    }
}
