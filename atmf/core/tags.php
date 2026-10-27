<?php

/**
 * ATMF tags handler. Part of ATMF core.
 * @version: ATMF-PHP Engine 1.1
 * @license: Apache-2.0 License
 * @repository: https://github.com/skito/ATMF-PHP
 */

namespace ATMF;

class Tag
{
    public $name;
    public $args;
    public $defaults;

    private static $_cache = [];
    private static $_cacheLimit = 4096;
    private static $_nonResolvingTags = ['#use', '#if', '#elseif', '#each'];
    private static $_conditionTags = ['#if', '#elseif'];
    private static $_operators = ['??', '==', '!=', '>=', '<=', '>', '<'];

    // Balanced {...} blocks, "quoted" strings, operators and words
    private static $_tokenPattern = '/(\{(?:[^{}]|(?1))*\})|"(.*)"|\s?(?:\?\?|==|!=|>=|<=|>|<)|\s?[a-z0-9\.\$\@\#\/_\-\!\|\&]+(?:\s+|$|(?=\?\?|==|!=|>=|<=|>|<))/imxU';

    /**
     * Constructor
     * @param string $name Tag name, e.g. $var, @resource, #function or /extension
     * @param array $args Tag arguments
     * @param array $defaults Fallback expressions, as set with the ?? operator
     */
    function __construct($name='', $args=[], $defaults=[]) {
        $this->name = $name;
        $this->args = $args;
        $this->defaults = $defaults;
    }

    private function BuildArgValues($sender)
    {
        $prefix = substr($this->name, 0, 1);
        $isCondition = in_array($this->name, self::$_conditionTags);

        $argValues = [];
        foreach($this->args as $arg)
        {
            if (!($arg instanceof Tag))
                $argValues[] = $arg;
            // Appended variables are passed by name and selected by the variables handler,
            // so a value starting with "$" is never mistaken for another variable
            elseif ($prefix == '$' && $arg->IsVariable())
                $argValues[] = $arg->name;
            // Resolved {...} blocks are values for the conditions, not expressions
            elseif ($isCondition)
                $argValues[] = new Literal($arg->Build($sender));
            else
                $argValues[] = $arg->Build($sender);
        }

        return $argValues;
    }

    private function BuildValue($sender)
    {
        $argValues = $this->BuildArgValues($sender);
        switch(substr($this->name, 0, 1))
        {
            case '$':
                return Variables::ProcessTag($sender, $this->name, $argValues);
            case '@':
                return Culture::ProcessTag($sender, $this->name, $argValues);
            case '#':
                return Functions::ProcessTag($sender, $this->name, $argValues);
            case '/':
                return Extensions::ProcessTag($sender, $this->name, $argValues);
        }

        return '';
    }

    /**
     * Build fallback expression. Values are appended, just like the variable arguments.
     */
    private function BuildExpression($sender, $args)
    {
        $values = [];
        foreach($args as $arg)
        {
            $values[] = $arg instanceof Tag ? $arg->Build($sender) : $arg;
        }

        // Single value keeps its type, e.g. an array passed to an extension
        if (count($values) == 1) return $values[0];

        $str = '';
        foreach($values as $value) $str .= self::ToString($value);
        return $str;
    }

    private function IsVariable()
    {
        return substr($this->name, 0, 1) == '$' && empty($this->args) && empty($this->defaults);
    }

    public function Build($sender)
    {
        $value = $this->BuildValue($sender);

        // Default operator, e.g. {$var ?? "default"}
        if (!empty($this->defaults))
        {
            foreach($this->defaults as $default)
            {
                if (!self::IsEmpty($value)) break;
                $value = $this->BuildExpression($sender, $default);
            }
        }

        return $value;
    }

    public function Set($sender, $value)
    {
        $argValues = $this->BuildArgValues($sender);
        switch(substr($this->name, 0, 1))
        {
            case '$':
                return Variables::SetTag($sender, $this->name, $argValues, $value);
            case '@':
                return Culture::SetTag($sender, $this->name, $argValues, $value);
            case '#':
                return Functions::SetTag($sender, $this->name, $argValues, $value);
            case '/':
                return Extensions::SetTag($sender, $this->name, $argValues, $value);
        }

        return false;
    }

    /**
     * Check whether a value is empty for the ?? operator
     * @param mixed $value Value to check
     * @return bool TRUE for NULL, empty string, FALSE or empty array. Zero is not empty.
     */
    public static function IsEmpty($value)
    {
        return $value === null || $value === '' || $value === false || $value === [];
    }

    /**
     * Convert value to string for the output, without notices or errors
     * @param mixed $value Value to convert
     * @return string
     */
    public static function ToString($value)
    {
        // Same output as the PHP conversion, but without the "Array to string" notice
        if (is_array($value)) return 'Array';
        if (is_object($value) && !method_exists($value, '__toString')) return '';

        return (string)$value;
    }

    public static function ParseStr($str='')
    {
        if (!is_string($str) || strlen($str) < 2 || !in_array(substr($str, 0, 1), ['#','$','@', '/']))
            return null;

        // Parsed tags are immutable, so the same markup (e.g. inside #each) is parsed once
        if (isset(self::$_cache[$str]))
            return self::$_cache[$str];

        if (count(self::$_cache) >= self::$_cacheLimit)
            self::$_cache = [];

        return self::$_cache[$str] = self::Parse($str);
    }

    private static function Parse($str)
    {
        // The name ends with the first whitespace or ?? operator
        preg_match('/^\S+?(?=\?\?|\s|$)/', $str, $nameMatch);

        $tag = new Tag($nameMatch[0]);
        $argStr = (string)substr($str, strlen($tag->name));
        if ($argStr === '') return $tag;

        preg_match_all(self::$_tokenPattern, $argStr, $cmdMatches);

        $resolve = !in_array($tag->name, self::$_nonResolvingTags);
        $isCondition = in_array($tag->name, self::$_conditionTags);
        $allowDefaults = substr($tag->name, 0, 1) != '#';

        // Arguments, followed by the fallback expressions of the ?? operator
        $segments = [[]];
        $segment = 0;
        foreach($cmdMatches[0] as $match)
        {
            if (substr($match, 0, 1) == '"') {
                $value = trim($match, '"');
                $segments[$segment][] = $isCondition ? new Literal($value) : $value;
                continue;
            }
            elseif (substr($match, 0, 1) == '{') {
                $segments[$segment][] = self::ParseStr(substr($match, 1, -1));
                continue;
            }

            $cmd = trim($match);
            if (in_array($cmd, self::$_operators, true)) {
                if ($cmd == '??') {
                    if ($allowDefaults) $segments[++$segment] = [];
                }
                // Comparison operators are meaningful in conditions only
                elseif ($isCondition) $segments[$segment][] = $cmd;
            }
            elseif (in_array(substr($cmd, 0, 1), ['$', '@', '#', '/'])) {
                $segments[$segment][] = $resolve ? self::ParseStr($cmd) : $cmd;
            }
            else $segments[$segment][] = $cmd;
        }

        $tag->args = array_shift($segments);
        $tag->defaults = $segments;

        return $tag;
    }

}

/**
 * Literal value inside a condition, e.g. "quoted" string or a resolved {...} block
 */
class Literal
{
    public $value;

    function __construct($value=null) {
        $this->value = $value;
    }
}
