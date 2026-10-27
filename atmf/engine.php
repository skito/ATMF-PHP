<?php

/**
 * ATMF Engine. Part of ATMF core.
 * @version: ATMF-PHP Engine 1.2
 * @license: Apache-2.0 License
 * @repository: https://github.com/skito/ATMF-PHP
 */

namespace ATMF {

    class Engine
    {
        public $vars = [];
        public $eVars = [];
        public $redundancyLimit = 32;
        public $allowGlobals = false;

        private $_templates = [];
        private $_cultureFolder = 'culture';
        private $_currentCulture = 'en-US';
        private $_templateDiscoveryPath = 'templates';
        private $_templateDiscoveryExtensions = ['tpl', 'html'];

        private $_callbacks = [];

        public static $latestInstance = null;

        /**
         * Constructor
         * @param bool $linkGlobalSelectors Whether to link gloval selector functions __ with this instance
         */
        public function __construct($linkGlobalSelectors=true)
        {
            if ($linkGlobalSelectors) self::$latestInstance = $this;
        }

        /**
         * Bind event callback
         * @param string $event - Event to bind for
         * @param callable $callback - Callable object
         * @return string Modified output depend on the event and input params
         */
        public function Bind($event, $callback)
        {
            if (!isset($this->_callbacks[$event]) || !is_array($this->_callbacks[$event]))
                $this->_callbacks[$event] = [];

            $this->_callbacks[$event][] = $callback;
        }

        /**
         * Parse ATMF markup
         * @param string $str ATMF markup (including mustage quotes)
         * @return string Processed markup
         */
        public function ParseMarkup($str)
        {
            $str = (string)$str;

            // ATMF Tags
            $parts = [];
            $tags = [];
            $copiedPos = 0;
            $openBlocks = 0;
            $disableParsing = 0;
            $indexEach = [];
            $startPos = -1;

            while(($startPos = strpos($str, '{', $startPos + 1)) !== FALSE)
            {
                // Skip escaping with backslash
                if ($startPos > 0 && $str[$startPos - 1] == '\\')
                    continue;

                $endPos = $this->FindClosingBracket($str, $startPos);
                if ($endPos === false)
                {
                    // Lone bracket in plain text (e.g. inline script) is not a tag
                    if (in_array(substr($str, $startPos + 1, 1), ['$', '@', '#', '/'], true))
                        die('ATMF Error: Closing curly bracket expected!');
                    continue;
                }
                elseif ($endPos - $startPos < 3) continue; // die('ATMF Error: Empty curly brackets detected!');

                // strip off top level brackets
                $blockStr = substr($str, $startPos + 1, $endPos - $startPos - 1);

                // Parse special functions
                if (strpos($blockStr, '#each') === 0 || strpos($blockStr, '#if') === 0)
                    $openBlocks++;

                if (strpos($blockStr, '#end') === 0 && strpos($blockStr, '#endlabel') !== 0)
                {
                    if (isset($indexEach[$openBlocks]))
                    {
                        if ($disableParsing > 0) $disableParsing--;
                        unset($indexEach[$openBlocks]);
                    }
                    $openBlocks--;
                }

                // Parse markup
                if ($disableParsing == 0)
                {
                    $tag = Tag::ParseStr($blockStr);
                    if ($tag != null)
                    {
                        // Tags are built after the scan, in order of appearance
                        $parts[] = substr($str, $copiedPos, $startPos - $copiedPos);
                        $tags[count($parts)] = $tag;
                        $parts[] = '';
                        $copiedPos = $endPos + 1;
                        $startPos = $endPos;
                    }
                }

                // Until the end of the last #each
                if (strpos($blockStr, '#each') === 0)
                {
                    $disableParsing++;
                    $indexEach[$openBlocks] = true;
                }
            }

            if (!empty($tags))
            {
                $parts[] = substr($str, $copiedPos);
                foreach($tags as $index => $tag)
                {
                    $parts[$index] = Tag::ToString($tag->Build($this));
                }
                $str = implode('', $parts);
            }

            // IF blocks, resulted by ATMF operations
            return $this->ResolveBlocks($str);
        }

        /**
         * (For internal use) Parsing of #each, #if, #else blocks
         * @param string $blockStr - As resulted from ParseMarkup operation
         * @return string - Processed markup
         */
        public function ParseBlocks($blockStr)
        {
            if (strpos($blockStr, '<%:block_start%><%:show%>') === 0)
            {
                return substr($blockStr, 25, strlen($blockStr) - 39);
            }
            elseif (strpos($blockStr, '<%:block_start%><%:each%>') === 0)
            {
                $resultStr = '';
                $str = substr($blockStr, 25, strlen($blockStr) - 39);
                $endTagPos = strpos($str, '%>');
                $eachTag = substr($str, 3, $endTagPos - 3);
                $str = substr($str, $endTagPos + 2);
                $eachArgs = explode(':', $eachTag);
                if (count($eachArgs) == 2)
                {
                    $collection = $eachArgs[0];
                    $item = $eachArgs[1];

                    $collectionArr = Variables::Select($this, $collection);
                    if (is_array($collectionArr) || $collectionArr instanceof \Traversable)
                    {
                        foreach($collectionArr as $row)
                        {
                            $this->eVars[] = [$item => $row];
                            $resultStr .= $this->ParseNestedMarkup($str);

                            if (count($this->eVars) > 0)
                                array_pop($this->eVars);
                        }
                    }
                }
                else $resultStr = '';

                return $resultStr;
            }


            return '';
        }

        /**
         * Resolve the blocks resulted by #if, #else, #each and #label functions
         * @param string $str Markup with tags already built
         * @return string Processed markup
         */
        private function ResolveBlocks($str)
        {
            $startPos = strpos($str, '<%:block_start%>');
            if ($startPos === false) return $str;

            $resultStr = '';
            $copiedPos = 0;
            while($startPos !== false)
            {
                $endPos = $this->FindBlockEnd($str, $startPos);
                if ($endPos === false)
                    die('ATMF Error: Closing function block expected!');

                $blockStr = substr($str, $startPos, $endPos - $startPos + 14);
                $resultStr .= substr($str, $copiedPos, $startPos - $copiedPos);

                // Visible blocks may contain nested blocks
                if (strpos($blockStr, '<%:block_start%><%:show%>') === 0)
                    $resultStr .= $this->ResolveBlocks((string)$this->ParseBlocks($blockStr));
                else
                    $resultStr .= $this->ParseBlocks($blockStr);

                $copiedPos = $endPos + 14;
                $startPos = strpos($str, '<%:block_start%>', $copiedPos);
            }

            return $resultStr.substr($str, $copiedPos);
        }

        /**
         * Find the end of a block, including the nested ones
         * @return int|false Position of the matching <%:block_end%>. FALSE if not found.
         */
        private function FindBlockEnd($str, $startPos)
        {
            $depth = 1;
            $nextStart = strpos($str, '<%:block_start%>', $startPos + 16);
            $nextEnd = strpos($str, '<%:block_end%>', $startPos + 16);
            while($nextEnd !== false)
            {
                if ($nextStart !== false && $nextStart < $nextEnd)
                {
                    $depth++;
                    $nextStart = strpos($str, '<%:block_start%>', $nextStart + 16);
                }
                else
                {
                    if (--$depth == 0) return $nextEnd;
                    $nextEnd = strpos($str, '<%:block_end%>', $nextEnd + 14);
                }
            }

            return false;
        }

        /**
         * Find the closing curly bracket, including the nested ones
         * @return int|false Position of the matching bracket. FALSE if not found.
         */
        private function FindClosingBracket($str, $startPos)
        {
            $depth = 0;
            $length = strlen($str);
            $pos = $startPos;
            while(($pos += strcspn($str, '{}', $pos)) < $length)
            {
                if ($str[$pos] == '{') $depth++;
                elseif (--$depth == 0) return $pos;
                $pos++;
            }

            return false;
        }

        /**
         * Parse markup resulted inside #each (e.g. by #template), while the loop variables are available
         * @param string $str ATMF markup
         * @return string Processed markup
         */
        private function ParseNestedMarkup($str)
        {
            $str = $this->ParseMarkup($str);
            for($i = 0; $i < $this->redundancyLimit && $this->HasMarkup($str); $i++)
            {
                $parsed = $this->ParseMarkup($str);
                if ($parsed === $str) break;
                $str = $parsed;
            }

            return $str;
        }

        /**
         * Check for ATMF tags which are not escaped
         * @param string $str Markup to check
         * @return bool
         */
        private function HasMarkup($str)
        {
            if (strpos($str, '{') === false) return false;

            foreach(['{$', '{@', '{#', '{/'] as $tagStart)
            {
                if (substr_count($str, $tagStart) > substr_count($str, '\\'.$tagStart))
                    return true;
            }

            return false;
        }

        /**
         * Global selector
         * @param string $key - ATMF markup
         * @param mixed $val - Value for setting
         * @return mixed Parsed ATMF markup if key only is provided. NONE/VOID if value is provided.
         */
        public function __($key, $val=null)
        {
            $tag = Tag::ParseStr($key);
            if ($tag == null) return;

            if ($val !== null) $tag->Set($this, $val);
            else return $tag->Build($this);
        }

        /**
         * Converts mustages in applicable HTML characters
         * @param mixed $str String to escape
         * @return string Escaped string
         */
        public function __escape($str)
        {
            return str_replace(['{', '}'], ['&lcub;', '&rcub;'], $str ?? '');
        }

        /**
         * Set culture folder path
         * @param mixed $path Path to the main culture folder
         */
        public function SetCultureFolder($path)
        {
            if ($this->_cultureFolder != $path)
            {
                $this->_cultureFolder = $path;
                Culture::ResetTranslations();
            }
        }

        /**
         * Get the current culture folder
         * @return string Path to the current culture folder
         */
        public function GetCultureFolder()
        {
            return $this->_cultureFolder;
        }

        /**
         * Set current culture
         * @param mixed $culture The culture as described in the culture folder
         */
        public function SetCulture($culture='')
        {
            if ($this->_currentCulture != $culture)
            {
                /*if (empty($culture) ||
                    !file_exists($this->_cultureFolder) ||
                    !is_dir($this->_cultureFolder))
                    die('ATMF Error: Culture '.$culture.' not found!');*/

                \ATMF\Culture::LoadSettings($this);
                $default = \ATMF\Culture::$settings['default'] ?? '';
                $locales = \ATMF\Culture::$settings['locale'] ?? [];

                // Without locale settings (culture.json), the culture is used as it is
                if (!empty($locales) && !isset($locales[$culture ?? '']))
                    $culture = $default;

                $this->_currentCulture = $culture;
                Culture::ResetTranslations();
            }
        }

        /**
         * Check if a culture is supported
         * @param string $culture Culture to check
         * @param bool $forceDefault Force default culture if the culture is not supported
         * @return bool True if the culture is supported. False otherwise.
         */
        public function IsSupportedCulture(&$culture, $forceDefault=false)
        {
            \ATMF\Culture::LoadSettings($this);
            $isSupported = isset(\ATMF\Culture::$settings['locale'][$culture]);
            if ($forceDefault && !$isSupported)
            {
                $default = \ATMF\Culture::$settings['default'] ?? '';
                $culture = $default;
            }

            return $isSupported;
        }

        /**
         * Get current culture
         * @return string Current culture string
         */
        public function GetCulture()
        {
            return $this->_currentCulture;
        }

        /**
         * Get template by name
         * @param string $name Template name
         * @return mixed String if the template exists. FALSE otherwise.
         */
        public function GetTemplate($name)
        {
            if (!empty( $this->_templates[$name]))
                return $this->_templates[$name];
            else {
                if ($this->DiscoverTemplate($name))
                    return $this->_templates[$name];
            }

            return false;
        }


        /**
         * Set template
         * @param mixed $name Name of the template
         * @param mixed $src Template source
         */
        public function SetTemplate($name, $src)
        {
            $this->_templates[$name] = $src;
        }

        /**
         * Set master template
         * @param mixed $src Template source
         */
        public function SetMasterTemplate($src)
        {
            $this->SetTemplate('master', $src);
        }

        /**
         * Set folder to auto discover templates
         * @param mixed $filepath Templates folder repository
         * @param mixed $extensions Extensions to loo for
         */
        public function SetTemplateDiscoveryPath($filepath=null, $extensions=['tpl', 'html'])
        {
            if (!is_array($extensions))
                $extensions = [$extensions];

            $this->_templateDiscoveryPath = $filepath;
            $this->_templateDiscoveryExtensions = $extensions;
        }

        public function DiscoverTemplate($name)
        {
            if ($this->_templateDiscoveryPath != null) {
                foreach($this->_templateDiscoveryExtensions as $ext) {
                    $filepath = $this->_templateDiscoveryPath.'/'.$name.'.'.$ext;
                    if (is_file($filepath)) {
                        $this->_templates[$name] = file_get_contents($filepath);
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Render specific template
         * @param string $name Name of the template
         * @param bool $capture Whether to return or write final output
         * @return string Final output
         */
        public function RendTemplate($name, $capture=false)
        {
            return $this->Rend($capture, $name);
        }

        /**
         * Render the final output
         * @param bool $capture Whether to return or write final output
         * @return string Final output
         */
        public function Rend($capture=false, $baseTemplate=null)
        {
            if (isset($this->_callbacks['beforerend']) && is_array($this->_callbacks['beforerend']))
            {
                foreach($this->_callbacks['beforerend'] as $callback)
                {
                    if (is_callable($callback))
                        $baseTemplate = call_user_func($callback, $baseTemplate, $capture);
                }
            }

            $output = '';
            if ($baseTemplate != null)
            {
                $output = $this->GetTemplate($baseTemplate);
                if (!$output) {
                    die('ATMF Warning: Rend failed! Template "'.$baseTemplate.'" not found!'."\r\n");
                }
            }
            else
            {
                $output = $this->GetTemplate('master');
                if (!$output) $output = $this->GetTemplate('page');
                if (!$output) {
                    die('ATMF Warning: Rend failed! Page and/or master template must be set!'."\r\n");
                }
            }

            $output = ''.$output;
            $redundancy = 0;
            while($this->HasMarkup($output))
            {
                if ($redundancy > $this->redundancyLimit)
                    die('ATMF Warning: Template redundancy limit has reached!'."\r\n");

                $parsed = $this->ParseMarkup($output);

                // Nothing else can be resolved, e.g. "{$}" in plain text
                if ($parsed === $output) break;

                $output = $parsed;
                $redundancy++;
            }

            // Replace escaped tags
            $output = str_replace(['\{$', '\{@', '\{#', '\{/'], ['{$', '{@', '{#', '{/'], $output);

            if (isset($this->_callbacks['afterrend']) && is_array($this->_callbacks['afterrend']))
            {
                foreach($this->_callbacks['afterrend'] as $callback)
                {
                    if (is_callable($callback))
                        $output = call_user_func($callback, $output, $baseTemplate, $capture);
                }
            }

            if ($capture) return $output;
            else echo $output;
        }

        /**
         * Load the ATMF engine dependencies
         * @param mixed $folder Folder path with .PHP dependencies
         */
        public static function LoadDependencies($folder='')
        {
            if (in_array(substr($folder, strlen($folder)-1), ['/', '\\']))
                $folder = substr($folder, 0, strlen($folder)-1);

            if (is_dir($folder))
            {
                foreach(scandir($folder) as $file)
                {
                    if (!in_array($file, ['.','..']))
                    {
                        $path = $folder.DIRECTORY_SEPARATOR.$file;
                        if (is_file($path) && strtolower(substr($path, -4)) == '.php')
                        {
                            require_once($path);
                        }
                        elseif (is_dir($path))
                        {
                            self::LoadDependencies($path);
                        }
                    }
                }
            }
        }
    }
}

namespace {

    \ATMF\Engine::LoadDependencies(__DIR__.'/core');
    \ATMF\Engine::LoadDependencies(__DIR__.'/ext');

    if (!function_exists('__')) {
        function __($key, $val=null) {
            if (\ATMF\Engine::$latestInstance != null)
                return \ATMF\Engine::$latestInstance->__($key, $val);
            else die('No ATMF instances found.');
        }
    }

    if (!function_exists('__escape')) {
        function __escape($str='') {
            if (\ATMF\Engine::$latestInstance != null)
                return \ATMF\Engine::$latestInstance->__escape($str);
            else die('No ATMF instances found.');
        }
    }
}
