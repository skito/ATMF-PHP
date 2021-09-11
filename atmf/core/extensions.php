<?php

namespace ATMF;

class Extensions
{
    private static $_extensions = [];

    public static function Register($name, $handler)
    {
        if (trim($name) == '') die('ATMF extension handler must have a name!');

        if ($handler instanceof Extension)
            self::$_extensions[trim($name)] = $handler;
        else die('ATMF extension handler must inherit the Extension interface!');
    }

    public static function GetAll()
    {
        return self::$_extensions;
    }

    public static function GetByName($name)
    {
        if (isset(self::$_extensions[$name]))
            return self::$_extensions[$name];
        else return null;
    }

    public static function ProcessTag($sender, $tagName, $args)
    {

        $extname = substr($tagName, 1);
        $handler = self::GetByName($extname);
        if ($handler == null) return '';

        $str = $handler->Get($args);

        /*if (isset($sender->vars[$varname])) $str .= $sender->vars[$varname];
        elseif ($sender->allowGlobals && isset($GLOBALS[$varname])) $str .= $GLOBALS[$varname];

        foreach($args as $arg)
        {
            if (substr($arg, 0, 1) == '$')
            {
                $varname = substr($arg, 1);
                if (isset($sender->vars[$varname])) $str .= $sender->vars[$varname];
                elseif ($sender->allowGlobals && isset($GLOBALS[$varname])) $str .= $GLOBALS[$varname];
            }
            else $str .= $arg;
        }*/

        return $str;
    }

    public static function SetTag($sender, $tagName, $args, $value)
    {
        $extname = substr($tagName, 1);
        $handler = self::GetByName($extname);
        if ($handler == null) return false;

        return $handler->Set($args, $value);
    }
}

interface Extension
{
    public function Get($args);
    public function Set($args, $value);
}