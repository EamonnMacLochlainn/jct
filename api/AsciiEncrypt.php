<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 11/12/2017
 * Time: 13:24
 */

namespace JCT;

// 2, 4, 5, 6
class AsciiEncrypt
{
    private static $key = 7427145;

    static function encrypt($str, $use_key = true)
    {
        $str = strtoupper(preg_replace('/\s/', '',$str));
        $str_array = str_split($str);

        $ascii_str = '';
        foreach($str_array as $char)
            $ascii_str .= ord($char);

        $total = ($use_key) ? (int)$ascii_str + self::$key : $ascii_str;

        return $total;
    }

    static function decrypt($str, $use_key = true)
    {
        $raw_ascii_val = ($use_key) ? (int)$str - self::$key : $str;

        $len = strlen($raw_ascii_val);
        $last = '';
        if($len%2)
        {
            $last = substr($raw_ascii_val, -3);
            $str_array = str_split($raw_ascii_val, 2);
            unset($str_array[ count($str_array) - 1 ]);
        }
        else
            $str_array = str_split($raw_ascii_val, 2);

        $str = '';
        foreach($str_array as $char)
            $str.= chr($char);

        if(!empty($last))
            $str.= chr($last);

        return $str;
    }
}