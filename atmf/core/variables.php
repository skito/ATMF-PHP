<?php

/**
 * ATMF variables handler. Part of ATMF core.
 * @version: ATMF-PHP Engine 1.1
 * @license: Apache-2.0 License
 * @repository: https://github.com/skito/ATMF-PHP
 */

namespace ATMF;

class Variables
{
    public static function ProcessTag($sender, $tagName, $args)
    {
        $varname = substr($tagName, 1);
        $str = self::Select($sender, $varname);
        if (empty($args)) return $str;

        $str = Tag::ToString($str);
        foreach($args as $arg)
        {
            if (is_string($arg) && substr($arg, 0, 1) == '$')
            {
                $varname = substr($arg, 1);
                $str .= Tag::ToString(self::Select($sender, $varname));
            }
            else $str .= Tag::ToString($arg);
        }

        return $str;
    }

    public static function SetTag($sender, $tagName, $args, $value)
    {
        $varname = substr($tagName, 1);
        $sender->vars[$varname] = $value;
        return true;
    }

    public static function Select($sender, $varname)
    {
        $var = null;
        $isAssigned = false;
        for($i=count($sender->eVars);$i > 0;$i--)
        {
            $eVars = $sender->eVars[$i-1];
            if (self::SelectQuery($eVars, $varname, $var)) {
                $isAssigned = true;
                break;
            }
        }

        if (!$isAssigned)
        {
            if (self::SelectQuery($sender->vars, $varname, $var)) {}
            elseif ($sender->allowGlobals && self::SelectQuery($GLOBALS, $varname, $var)) {}
        }

        return $var;
    }

    public static function SelectQuery($collection, $selector, &$var)
    {
        if (is_string($selector))
            $selector = explode('.', $selector);

        // Keys containing dots, e.g. $vars['user.name']
        if (count($selector) > 1 && is_array($collection))
        {
            $selectorStr = implode('.', $selector);
            if (isset($collection[$selectorStr]))
            {
                $var = $collection[$selectorStr];
                return true;
            }
        }

        $isFound = self::SelectKey($collection, $selector[0], $value);

        if (count($selector) > 1)
        {
            if ($isFound && (is_array($value) || is_object($value)))
                return self::SelectQuery($value, array_slice($selector, 1), $var);
            else return false;
        }
        elseif ($isFound)
        {
            $var = $value;
            return true;
        }

        $var = '';
        return false;
    }

    /**
     * Select single key of an array or an object (including ArrayAccess objects)
     * @return bool TRUE if the key is set
     */
    private static function SelectKey($collection, $key, &$value)
    {
        if (is_array($collection))
        {
            if (!isset($collection[$key])) return false;
            $value = $collection[$key];
            return true;
        }
        elseif (is_object($collection) && $key !== '')
        {
            if (isset($collection->$key))
                $value = $collection->$key;
            elseif ($collection instanceof \ArrayAccess && isset($collection[$key]))
                $value = $collection[$key];
            else return false;

            return true;
        }

        return false;
    }
}
