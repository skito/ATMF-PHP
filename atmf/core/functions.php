<?php

/**
 * ATMF functions handler. Part of ATMF core.
 * @version: ATMF-PHP Engine 1.1
 * @license: Apache-2.0 License
 * @repository: https://github.com/skito/ATMF-PHP
 */

namespace ATMF;

class Functions
{
    private static $_lastConditionResults = [];
    private static $_comparisonOperators = ['==', '!=', '>=', '<=', '>', '<'];

    public static function ProcessTag($sender, $tagName, $args)
    {
        switch($tagName)
        {
            case '#template':
                $name = Tag::ToString($args[0] ?? '');
                $label = Tag::ToString($args[1] ?? '');
                $template = $sender->GetTemplate($name);
                if (!empty($template) && $label != '')
                {
                    preg_match_all('/\{\#label\s+([a-z0-9-_\.]+)(?:\s+hidden)?\s*\}(.*)\{\#endlabel\}/imsxU', $template, $labels);
                    foreach($labels[0] as $key => $match)
                    {
                        $lblname = $labels[1][$key];
                        if ($lblname == $label)
                            return $labels[2][$key];
                    }
                    return '';
                }
                return $template;
            case '#label':
                $hidden = $args[1] ?? '';
                return $hidden != 'hidden' ? '<%:block_start%><%:show%>' : '<%:block_start%><%:hide%>';
            case '#endlabel':
                return '<%:block_end%>';
            case '#use':
                $path = Tag::ToString($args[0] ?? '');
                $operator = isset($args[1]) ? Tag::ToString($args[1]) : 'as';
                if ($path != '' && $operator == 'as')
                {
                    $keypath = (strlen($path) > 1) ? trim(substr($path, 1)) : '';
                    $alias = isset($args[2]) ? Tag::ToString($args[2]) : $keypath;
                    if ($keypath != '')
                    {
                        Culture::AddAlias($sender, $alias, $keypath);
                        return '';
                    }
                }
                return '';
            case '#if':
                $result = self::EvaluateCondition($sender, $args);
                self::$_lastConditionResults[] = !$result;
                return $result ? '<%:block_start%><%:show%>' : '<%:block_start%><%:hide%>';
            case '#endif':
            case '#end':
                if (count(self::$_lastConditionResults) > 0)
                    array_pop(self::$_lastConditionResults);

                return '<%:block_end%>';
            case '#elseif':
                // Shown only if no previous branch is shown and the condition is true
                $count = count(self::$_lastConditionResults);
                $result = $count > 0 && self::$_lastConditionResults[$count-1] && self::EvaluateCondition($sender, $args);
                if ($result) self::$_lastConditionResults[$count-1] = false;
                return $result ? '<%:block_end%><%:block_start%><%:show%>' : '<%:block_end%><%:block_start%><%:hide%>';
            case '#else':
                $count = count(self::$_lastConditionResults);
                $lastCondResult = ($count > 0) ? self::$_lastConditionResults[$count-1] : false;
                return $lastCondResult ? '<%:block_end%><%:block_start%><%:show%>' : '<%:block_end%><%:block_start%><%:hide%>';
            case '#each':
                self::$_lastConditionResults[] = false; // Hollow push, it will be removed at #end match
                $args = array_map([Tag::class, 'ToString'], $args);
                if (count($args) == 3 && in_array(trim($args[1]), ['as', 'in']))
                {
                    $operator = trim($args[1]);
                    $collection = trim($operator == 'as' ? $args[0] : $args[2]);
                    $item = trim($operator == 'as' ? $args[2] : $args[0]);

                    if (strlen($collection) < 2 || strlen($item) < 2) die('ATMF Error: Wrong #each syntax!');

                    return '<%:block_start%><%:each%><%:'.substr($collection, 1).':'.substr($item, 1).'%>';
                }
                else die('ATMF Error: Wrong #each syntax!');
        }
    }

    public static function SetTag($sender, $tagName, $args, $value)
    {
        switch($tagName)
        {
            case '#template':
                $name = Tag::ToString($args[0] ?? '');
                if ($name != '')
                {
                    $sender->SetTemplate($name, $value);
                    return true;
                }
                else return false;
        }

        return false;
    }

    /**
     * Evaluate #if condition, e.g. {#if $total >= 10 && !$disabled || $isAdmin}
     * Operands without && or || between them are joined with &&. The && takes precedence over ||.
     * @param mixed $sender ATMF engine
     * @param array $args Condition arguments
     * @return bool Condition result
     */
    private static function EvaluateCondition($sender, $args)
    {
        // Connectors and operators are kept as strings, operands as [value, negate] pairs
        $tokens = [];
        $negate = false;
        foreach($args as $arg)
        {
            if (!($arg instanceof Literal))
            {
                $arg = trim(Tag::ToString($arg));
                if ($arg === '') continue;

                if (in_array($arg, ['&&', '||'], true) || in_array($arg, self::$_comparisonOperators, true))
                {
                    $tokens[] = $arg;
                    continue;
                }

                // Single "!" negates the next operand
                $name = ltrim($arg, '!');
                if ((strlen($arg) - strlen($name)) % 2 == 1) $negate = !$negate;
                if ($name === '') continue;
                $arg = $name;
            }

            $tokens[] = [$arg, $negate];
            $negate = false;
        }

        $result = null; // Result of the current && group, NULL until its first operand
        $count = count($tokens);
        for($i = 0; $i < $count; $i++)
        {
            $token = $tokens[$i];
            if ($token === '||')
            {
                if ($result === true) return true;
                $result = null;
                continue;
            }
            elseif (!is_array($token)) continue;

            $operator = null;
            if (isset($tokens[$i + 2]) &&
                is_string($tokens[$i + 1]) &&
                in_array($tokens[$i + 1], self::$_comparisonOperators, true) &&
                is_array($tokens[$i + 2]))
            {
                $operator = $tokens[$i + 1];
                $right = $tokens[$i + 2];
                $i += 2;
            }

            // The group is already false, no need to evaluate the rest of it
            if ($result === false) continue;

            $value = self::OperandValue($sender, $token, $operator !== null);
            if ($operator !== null)
                $value = self::Compare($value, $operator, self::OperandValue($sender, $right, true));

            $result = (bool)$value;
        }

        return $result === true;
    }

    private static function OperandValue($sender, $operand, $isCompared)
    {
        list($value, $negate) = $operand;

        if ($value instanceof Literal)
            $value = $value->value;
        else
        {
            switch(substr($value, 0, 1))
            {
                case '$':
                    $value = Variables::ProcessTag($sender, $value, []);
                    break;
                case '@':
                    $value = Culture::ProcessTag($sender, $value, []);
                    break;
                case '/':
                    $value = Extensions::ProcessTag($sender, $value, []);
                    break;
                default:
                    $value = self::LiteralValue($value, $isCompared);
            }
        }

        return $negate ? !$value : $value;
    }

    /**
     * Value of unquoted word - number, true, false, null or text.
     * Unquoted text is FALSE when it's not compared, as in the previous versions.
     */
    private static function LiteralValue($word, $isCompared)
    {
        if (is_numeric($word)) return $word + 0;

        switch(strtolower($word))
        {
            case 'true': return true;
            case 'false': return false;
            case 'null': return null;
        }

        return $isCompared ? $word : false;
    }

    private static function Compare($left, $operator, $right)
    {
        $cmp = self::CompareValues($left, $right);
        switch($operator)
        {
            case '==': return $cmp === 0;
            case '!=': return $cmp !== 0;
            case '>': return $cmp !== null && $cmp > 0;
            case '<': return $cmp !== null && $cmp < 0;
            case '>=': return $cmp !== null && $cmp >= 0;
            case '<=': return $cmp !== null && $cmp <= 0;
        }

        return false;
    }

    /**
     * Compare two values, consistently across PHP versions
     * @return int|null Negative, zero or positive. NULL if the values can't be compared.
     */
    private static function CompareValues($left, $right)
    {
        if (is_object($left) && method_exists($left, '__toString')) $left = (string)$left;
        if (is_object($right) && method_exists($right, '__toString')) $right = (string)$right;

        // Collections compared to numbers use the count of items, e.g. {#if $items > 0}
        if (is_numeric($right) && (is_array($left) || $left instanceof \Countable)) $left = count($left);
        if (is_numeric($left) && (is_array($right) || $right instanceof \Countable)) $right = count($right);

        if (is_numeric($left) && is_numeric($right))
            return ($left + 0) <=> ($right + 0);

        if (is_bool($left) || is_bool($right))
            return (bool)$left <=> (bool)$right;

        $leftIsScalar = is_scalar($left) || $left === null;
        $rightIsScalar = is_scalar($right) || $right === null;
        if ($leftIsScalar && $rightIsScalar)
            return strcmp((string)$left, (string)$right);

        if (!$leftIsScalar && !$rightIsScalar)
            return $left == $right ? 0 : null;

        return null;
    }
}
