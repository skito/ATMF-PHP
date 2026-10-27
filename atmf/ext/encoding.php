<?php

namespace ATMF\CoreExtensions;

class JsonEncode implements \ATMF\Extension
{
    public function __construct() {}
    public function Get($args)
    {
        $obj = $args[0] ?? null;
        $flags = explode('|', \ATMF\Tag::ToString($args[1] ?? ''));

        $jsonFlags = JSON_UNESCAPED_SLASHES;

        // Escape unicode, unless "unicode" flag
        if (in_array('unicode', $flags))
            $jsonFlags |= JSON_UNESCAPED_UNICODE;

        if ($obj != null)
            return json_encode($obj, $jsonFlags);
        else
            return '';
    }

    public function Set($args, $value) {return false;}
}
\ATMF\Extensions::Register('json_encode', new JsonEncode());

class JsonEncodeBase64 extends JsonEncode
{
    public function Get($args)
    {
        $str = parent::Get($args);
        return base64_encode($str);
    }


}
\ATMF\Extensions::Register('json_encode_base64', new JsonEncodeBase64());

class Base64Encode implements \ATMF\Extension
{
    public function __construct() {}
    public function Get($args)
    {
        $str = \ATMF\Tag::ToString($args[0] ?? '');
        $trim = ($args[1] ?? '') == 'trim';

        if ($str != '')
            return $trim ? str_replace('=', '',base64_encode($str)) : base64_encode($str);
        else
            return '';
    }

    public function Set($args, $value) {return false;}
}
\ATMF\Extensions::Register('base64_encode', new Base64Encode());

class UrlEncode implements \ATMF\Extension
{
    public function __construct() {}
    public function Get($args)
    {
        $str = \ATMF\Tag::ToString($args[0] ?? '');
        if ($str != '')
            return urlencode($str);
        else
            return '';
    }

    public function Set($args, $value) {return false;}
}
\ATMF\Extensions::Register('urlencode', new UrlEncode());