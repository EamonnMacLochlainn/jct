<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 21/06/2016
 * Time: 10:38
 */

namespace JCT;

use Exception;
use DateTime;

class Helper
{
    static private $libphonenumber_included = false;
    static public $libphonenumber_error;

    static function show($data, $return = false)
    {
        $str = '<pre>' . print_r($data, true) . '</pre>';
        if(!$return)
            echo $str;
        else
            return $str;
    }

    static function show_exception_array($data, $return = true)
    {
        $str = '<div class="dialog-status-array"><pre>' . print_r($data, true) . '</pre></div>';
        if(!$return)
            echo $str;
        else
            return $str;
    }

    static function export($data)
    {
        echo '<pre>' . var_export($data, true) . '</pre>';
    }

    static function write($data, $append = false, $options = [])
    {
        $data = print_r($data, true);
        $text = $data . PHP_EOL . PHP_EOL . PHP_EOL;

        $label = (!empty($options['label'])) ? $options['label'] . PHP_EOL : null;
        $file_name = (!empty($options['file_name'])) ? $options['file_name'] : 'tmp';
        $file_ext = (!empty($options['file_ext'])) ? $options['file_ext'] : 'txt';
        $file_ext = ltrim($file_ext, '.');

        $path = JCT_PATH_ROOT . $file_name . '.' . $file_ext;

        if($append)
            file_put_contents($path, $label . $text, FILE_APPEND);
        else
            file_put_contents($path, $label . $text);
    }

    static function write_dump($data, $append = false, $options = [])
    {
        ob_start();
        var_dump($data);
        $data = ob_get_clean();

        $text = $data . PHP_EOL . PHP_EOL . PHP_EOL;

        $file_name = (!empty($options['file_name'])) ? $options['file_name'] : 'tmp';
        $file_ext = (!empty($options['file_ext'])) ? $options['file_ext'] : 'txt';
        $file_ext = ltrim($file_ext, '.');

        $path = JCT_PATH_ROOT . $file_name . '.' . $file_ext;

        if($append)
            file_put_contents($path, $text, FILE_APPEND);
        else
            file_put_contents($path, $text);
    }

    static function generate_call_trace()
    {
        $e = new Exception();
        $trace = explode("\n", $e->getTraceAsString());

        // reverse array to make steps line up chronologically
        $trace = array_reverse($trace);
        array_shift($trace); // remove {main}
        array_pop($trace); // remove call to this method
        $length = count($trace);

        $result = [];
        for ($i = 0; $i < $length; $i++)
            $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' '));

        return "\t" . implode("\n\t", $result);
    }

    static function slugify($string, $sub_string = null)
    {
        $string = self::latinise_string($string);
        $sub_string = (is_null($sub_string)) ? '-' : $sub_string;

        $string = preg_replace('~[^\\pL\d]+~u', $sub_string, $string);
        $string = trim($string, $sub_string);
        $string = preg_replace('~[^-\w]+~', '', $string);

        return $string;
    }

    static function get_char_for_index($index)
    {
        $n = 0;
        $char = '';
        for($i='A'; $i!='AAAA'; $i++)
        {
            if($n != $index)
            {
                $n++;
                continue;
            }

            $char = $i;
            break;
        }

        return $char;
    }

    static function clean_unicode_literals($string)
    {
        return preg_replace_callback('@\\\(x)?([0-9a-zA-Z]{2,3})@',
            function ($m) {
                if ($m[1]) {
                    $hex = substr($m[2], 0, 2);
                    $unhex = chr(hexdec($hex));
                    if (strlen($m[2]) > 2) {
                        $unhex .= substr($m[2], 2);
                    }
                    return $unhex;
                } else {
                    return chr(octdec($m[2]));
                }
            }, $string);
    }

    static function latinise_string($string)
    {
        if(empty($string))
            return null;

        $string = preg_replace('/[^ \w]+/', '', $string);
        if(empty($string))
            return null;

        $string = mb_convert_case($string, MB_CASE_LOWER, "UTF-8");
        $table = [
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'ă'=>'a', 'ā'=>'a', 'ą'=>'a', 'æ'=>'a', 'ǽ'=>'a',
            'Þ'=>'b', 'þ'=>'b', 'ß'=>'ss',
            'ç'=>'c', 'č'=>'c', 'ć'=>'c', 'ĉ'=>'c', 'ċ'=>'c',
            'đ'=>'dj', 'ď'=>'d',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'ę'=>'e', 'ė'=>'e',
            'ĝ'=>'g', 'ğ'=>'g', 'ġ'=>'g', 'ģ'=>'g',
            'ĥ'=>'h', 'ħ'=>'h',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'į'=>'i', 'ĩ'=>'i', 'ī'=>'i', 'ĭ'=>'i', 'ı'=>'i',
            'ĵ'=>'j',
            'ķ'=>'k', 'ĸ'=>'k',
            'ĺ'=>'l', 'ļ'=>'l', 'ľ'=>'l', 'ŀ'=>'l', 'ł'=>'l',
            'ñ'=>'n', 'ń'=>'n', 'ň'=>'n', 'ņ'=>'n', 'ŋ'=>'n', 'ŉ'=>'n',
            'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ō'=>'o', 'ŏ'=>'o', 'ő'=>'o', 'œ'=>'o', 'ð'=>'o',
            'ŕ'=>'r', 'ř'=>'r', 'ŗ'=>'r',
            'š'=>'s', 'ŝ'=>'s', 'ś'=>'s', 'ş'=>'s',
            'ŧ'=>'t', 'ţ'=>'t', 'ť'=>'t',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ũ'=>'u', 'ū'=>'u', 'ŭ'=>'u', 'ů'=>'u', 'ű'=>'u', 'ų'=>'u',
            'ŵ'=>'w', 'ẁ'=>'w', 'ẃ'=>'w', 'ẅ'=>'w',
            'ý'=>'y', 'ÿ'=>'y', 'ŷ'=>'y',
            'ž'=>'z', 'ź'=>'z', 'ż'=>'z'
        ];
        $string = strtr($string, $table);
        return $string;
    }

    static function generate_random_string($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);

        $randomString = '';
        for($i = 0; $i < $length; $i++)
            $randomString .= $characters[rand(0, $charactersLength - 1)];

        return $randomString;
    }

    static function hash_password($pass)
    {
        return password_hash($pass, CRYPT_BLOWFISH, ['cost'=>8]);
    }

    static function array_chunk(Array $array, $num_chunks, $chunk_limit = null)
    {
        $array_len = count($array);

        if( ($num_chunks === -1) && (!is_null($chunk_limit)) )
        {
            $chunk_len = intval($chunk_limit);
            $num_chunks = ceil($array_len / $chunk_limit);
        }
        else
            $chunk_len = floor($array_len / $num_chunks);

        $chunk_remainder = $array_len % $num_chunks;

        $chunked_array = [];
        $mark = 0;
        for($i = 0; $i < $num_chunks; $i ++)
        {
            $incr = ($i < $chunk_remainder) ? $chunk_len + 1 : $chunk_len;
            $chunked_array[$i] = array_slice($array, $mark, $incr);
            $mark += $incr;
        }

        return $chunked_array;
    }

    static function lname_as_index($lname)
    {
        if(empty($lname))
            return null;

        $lname = preg_replace('/[^ \w]+/', '', $lname);
        if(empty($lname))
            return null;

        // Mac hEi...
        // NihUl...
        $has_seimhiu = false;
        $possible_familials = ['MAC','MC','NIC','NÍ','O','Ó','UI','UÍ'];
        $fam_sub = (strlen($lname) > 5) ? strtoupper(substr($lname, 0, 3)) : null;
        if(in_array($fam_sub, $possible_familials))
        {
            $remainder = trim( substr($lname, 3) );
            $has_seimhiu = (substr($remainder, 0, 1) == 'h');
        }
        $fam_sub = (strlen($lname) > 4) ? strtoupper(substr($lname, 0, 2)) : null;
        if(in_array($fam_sub, $possible_familials))
        {
            $remainder = trim( substr($lname, 2) );
            $has_seimhiu = (substr($remainder, 0, 1) == 'h');
        }
        $fam_sub = (strlen($lname) > 3) ? strtoupper(substr($lname, 0, 1)) : null;
        if(in_array($fam_sub, $possible_familials))
        {
            $remainder = trim( substr($lname, 1) );
            $has_seimhiu = (substr($remainder, 0, 1) == 'h');
        }

        // latinise (& strtolower) and remove spaces, punctuation
        $lname_as_index = self::latinise_string($lname);
        $lname_as_index = preg_replace("/[^[:alpha:]]/u", '', $lname_as_index);

        // remove starting mac / mc / nic / ni / o
        $sub_str_3 = (strlen($lname_as_index) > 3) ? substr($lname_as_index, 0, 3) : null;
        $sub_str_2 = (strlen($lname_as_index) > 2) ? substr($lname_as_index, 0, 2) : null;
        $sub_str_1 = substr($lname_as_index, 0, 1);

        if( (!is_null($sub_str_3)) && (in_array($sub_str_3, ['mac', 'nic'])) )
            $lname_as_index = substr($lname_as_index, 3);
        else if( (!is_null($sub_str_2)) && (in_array($sub_str_2, ['mc', 'ni', 'ui'])) )
            $lname_as_index = substr($lname_as_index, 2);
        else if($sub_str_1 == 'o')
            $lname_as_index = substr($lname_as_index, 1);

        if($has_seimhiu)
            $lname_as_index = substr($lname_as_index, 1);

        return $lname_as_index;
    }

    static function determine_displayed_name($salutation = null, $fname = null, $lname = null, $salute_name = null)
    {
        if(!empty($salute_name))
            return $salute_name;

        if( (empty($salutation)) && (empty($fname)) && (empty($lname)) )
            return null;

        $salt = (empty($salutation)) ? '' : $salutation . ' ';
        $fname = (empty($fname)) ? '' : $fname . ' ';

        return $salt . $fname . $lname;
    }

    static function require_libphonenumber()
    {
        $root = JCT_PATH_CORE_VENDORS . 'libphonenumber';
        try
        {
            if(!is_dir($root))
                throw new Exception('Class LibPhoneNumber directory not found.');


            foreach (scandir($root) as $filename)
            {
                if(in_array($filename, ['.','..']))
                    continue;
                if(strpos($filename, 'Interface') === false)
                    continue;

                $path = $root . JCT_DE . $filename;
                if(is_file($path))
                    require_once $path;
            }

            foreach (scandir($root) as $filename)
            {
                if(in_array($filename, ['.','..']))
                    continue;

                $path = $root . JCT_DE . $filename;
                if (is_file($path))
                    require_once $path;
            }

            self::$libphonenumber_included = true;
            return true;
        }
        catch(Exception $e)
        {
            return $e->getMessage();
        }
    }

    static function normalise_contact_number($number, $default_region = null)
    {
        self::$libphonenumber_error = null;

        try
        {
            if(!self::$libphonenumber_included)
            {
                $tmp = self::require_libphonenumber();
                if($tmp !== true)
                    throw new Exception($tmp);
            }

            $number = preg_replace("/[^0-9+]/", "", $number);
            if(empty($number))
                throw new Exception('Null value detected.');

            $region = (!is_null($default_region)) ? $default_region : 'IE';

            $phone_util = \libphonenumber\PhoneNumberUtil::getInstance();

            $first_two_chars = substr($number, 0, 2);
            if($first_two_chars == '00')
                $number = substr($number, 2);

            $first_char = substr($number, 0, 1);
            if($first_char != '0')
            {
                $first_two_chars = substr($number, 0, 2);
                switch($first_two_chars)
                {
                    case('44'):
                        $region = 'GB';
                        break;
                    default:
                        // leave at default region
                        break;
                }
            }

            $number_prototype = $phone_util->parse($number, $region);
            $valid_number = $phone_util->isValidNumber($number_prototype);
            if(!$valid_number)
                throw new Exception('Invalid number detected.');

            return $phone_util->format($number_prototype, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
        }
        catch(Exception $e)
        {
            self::$libphonenumber_error = $e->getMessage();
            return null;
        }
    }

    static function normalise_person_parameters($person, $salutations = [])
    {
        $person = array_change_key_case($person, CASE_LOWER);

        if(!empty($person['id']))
            $person['id'] = intval($person['id']);

        if(!empty($person['active']))
            $person['active'] = (intval($person['active']) > 0) ? 1 : 0;

        if(!empty($person['email']))
        {
            $email = strtolower($person['email']);
            $email = str_replace(' ', '', $email);
            if(!empty($email))
                $email = (filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
            else
                $email = null;

            $person['email'] = $email;
        }

        if(!empty($person['mobile']))
            $person['mobile'] = self::normalise_contact_number($person['mobile']);

        if(!empty($person['fname']))
        {
            $fname = trim( preg_replace('/\s+/', ' ',$person['fname']) );
            if(!empty($fname))
            {
                $parts = explode(' ', $fname);
                $parts = array_map('strtolower', $parts);
                $parts = array_map('ucwords', $parts);
                $fname = implode(' ', $parts);
            }
            else
                $fname = null;

            $person['fname'] = $fname;
        }

        if(!empty($person['lname']))
        {
            $lname = trim( preg_replace('/\s+/', ' ',$person['lname']) );
            if(!empty($lname))
            {
                $lname = strtolower($lname);

                $parts = explode('\'', $lname);
                $parts = array_map('trim', $parts);
                $parts = array_map('ucwords', $parts);
                $lname = implode('\'', $parts);

                $parts = explode('-', $lname);
                $parts = array_map('trim', $parts);
                $parts = array_map('ucwords', $parts);
                $lname = implode('', $parts);

                $parts = explode(' ', $lname);
                $parts = array_map('trim', $parts);
                $parts = array_map('ucwords', $parts);
                $lname = implode(' ', $parts);
            }
            else
                $lname = null;

            $person['lname'] = $lname;
        }

        if(!empty($person['indexed_lname']))
            $person['indexed_lname'] = self::lname_as_index($person['indexed_lname']);

        if(!empty($person['alias']))
        {
            $alias = trim( preg_replace('/\s+/', ' ',$person['alias']) );
            if(!empty($alias))
            {
                $alias = strtolower($alias);

                $parts = explode('\'', $alias);
                $parts = array_map('trim', $parts);
                $parts = array_map('ucwords', $parts);
                $alias = implode('\'', $parts);

                $parts = explode('-', $alias);
                $parts = array_map('trim', $parts);
                $parts = array_map('ucwords', $parts);
                $alias = implode('', $parts);

                $parts = explode(' ', $alias);
                $parts = array_map('trim', $parts);
                $parts = array_map('ucwords', $parts);
                $alias = implode(' ', $parts);
            }
            else
                $alias = null;

            $person['alias'] = $alias;
        }

        if(!empty($person['salt_id']))
            $person['salt_id'] = abs(intval($person['salt_id']));

        if( (empty($person['salt_id'])) && (!empty($person['alias'])) && (!empty($salutations)) )
        {
            $tmp = strtolower($person['alias']);
            $parts = explode(' ', $tmp);
            if(count($parts) > 0)
            {
                $str = preg_replace('/\PL/u', '', $parts[0]);
                $person['salt_id'] = (array_key_exists($str, $salutations)) ? $salutations[$str] : 0;
            }
        }

        return $person;
    }

    static function nullify_empty_values($arr)
    {
        $tmp = [];
        foreach($arr as $k => $v)
            $tmp[$k] = (empty($v)) ? null : $v;

        return $tmp;
    }

    static function get_excel_col_char($col_count)
    {
        $alphabet = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        array_unshift($alphabet, "tmp");
        unset($alphabet[0]);
        $num_alphabet = count($alphabet);

        if(isset($alphabet[ $col_count ]))
            $col = $alphabet[ $col_count ];
        else
        {
            $tmp_count = ($col_count % $num_alphabet);
            if($tmp_count)
            {
                $suffix_col_count = intval(floor( $col_count / $num_alphabet));
                $suffix = $alphabet[ $suffix_col_count ];
                $col = $suffix . $alphabet[ $tmp_count ];
            }
            else
            {
                $suffix_col_count = intval(floor( ($col_count-1) / $num_alphabet));
                $suffix = $alphabet[ $suffix_col_count ];
                $col = $suffix . $alphabet[ $num_alphabet ];
            }
        }

        return $col;
    }

    static function prep_org_media_dir($org_guid, $media_type, $is_download = true, $clear_old = false)
    {
        try
        {
            if(!in_array($media_type, ['documents','images','zips']))
                throw new Exception('Could not create directory due to unrecognised media type.');

            if(!is_dir(JCT_PATH_MEDIA . $org_guid))
                mkdir(JCT_PATH_MEDIA . $org_guid);

            if(!is_dir(JCT_PATH_MEDIA . $org_guid . JCT_DE . $media_type))
                mkdir(JCT_PATH_MEDIA . $org_guid . JCT_DE . $media_type);

            $target_dir = JCT_PATH_MEDIA . $org_guid . JCT_DE . 'documents' . JCT_DE;
            $target_url = JCT_URL_MEDIA . $org_guid . '/documents/';

            if (!is_readable($target_dir . 'index.php'))
                file_put_contents($target_dir . 'index.php', '<?php //index');

            if($is_download)
            {
                if(!is_dir($target_dir . 'downloads'))
                    mkdir($target_dir . 'downloads');

                $target_dir.= 'downloads' . JCT_DE;
                $target_url.= 'downloads/';
            }

            if (!is_readable($target_dir . 'index.php'))
                file_put_contents($target_dir . 'index.php', '<?php //index');

            if($clear_old)
            {
                $now = new DateTime();
                $now_u = $now->format('U');
                $dir_iterator = new \RecursiveDirectoryIterator($target_dir);
                $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);

                foreach ($iterator as $file)
                {
                    /*if($file->isDot())
                        continue;*/
                    if($file->getFilename() === 'index.php')
                        continue;

                    if ($file->isFile())
                    {
                        /*$modified = $file->getMTTime();
                        if( ($now_u - $modified) > (10 * 60) )
                            unlink($file->getPathname());*/
                    }
                }
            }

            return ['path'=>$target_dir, 'url'=>$target_url];
        }
        catch(Exception $e)
        {
            return ['error'=>'Helper `prep_org_media_dir` failed: ' . $e->getMessage()];
        }
    }

    static function read_csv_to_array($source_path, $first_row_as_fields = true, $delimiter = ',')
    {
        try
        {
            $handle = fopen($source_path, 'r');
            if(!$handle)
                throw new Exception('File could not be read');

            $rows = $fields = [];
            $i = 0;
            while (($row = fgetcsv($handle, 4096, $delimiter)) !== false)
            {
                if($first_row_as_fields && ($i == 0))
                    $fields = array_map('strtolower', $row);
                else
                {
                    foreach($row as $n => $value)
                    {
                        if($first_row_as_fields)
                            $rows[$i][ $fields[$n] ] = $value;
                        else
                            $rows[$i][] = $value;
                    }
                }

                $i++;
            }
            if (!feof($handle))
                throw new Exception("Unexpected fgets() fail");

            fclose($handle);

            return $rows;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }


























    private static $crypto_ascii_key = '';
    private static $recursion_max_depth = 3;

    /**
     * @param $method
     * @param $severity
     * @param Exception $e
     *
     * Populates Core::$screen_errors, while also writing to log
     */
    static function set_error($method, $severity, Exception $e)
    {
        /*$code = $e->getCode();
        $line = $e->getLine();
        $message = $e->getMessage();

        $d = new DateTime();
        $date_str = $d->format('Y-m-d');

        // write to file
        $file_path = DS_PATH_CORE . 'error.log';
        $str = $date_str . ' ' . strtoupper($severity) . ': (' . $method . ') ' . $message . ' [' . $code . '] @ line ' . $line . PHP_EOL;
        file_put_contents($file_path, $str, FILE_APPEND);

        // insert to core error array
        $severity = strtolower(trim($severity));
        if(!array_key_exists($severity, Core::$screen_errors))
        {
            $severity = 'notice';
            $message = '(Severity not found) ' . $str;
        }

        if(in_array($severity, ['build', 'fatal']))
            $message = $method . ' has thrown the following ' . $severity . ' error: ' . $str;

        Core::$screen_errors[ $severity ][] = $message;*/
    }

    /*static function build_short_address_str($ind)
    {
        $arr = [];
        $house_num = (!empty($ind['house_num'])) ? $ind['house_num'] . ' ' : '';

        if(!empty($ind['add_1']))
            $arr[] = $house_num . $ind['add_1'];
        if(!empty($ind['add_2']))
            $arr[] = $ind['add_2'];
        if(!empty($ind['add_3']))
            $arr[] = $ind['add_3'];

        $str = implode(', ', $arr);
        if(strlen($str) > 50)
        {
            $string = wordwrap($str, 50, "##", true);
            $arr = explode("##", $string);
            $str = rtrim(trim($arr[0]),',');
        }

        return $str;
    }*/


    /**
     * @param $e == the data array for the user or org
     * @return array
     *
     * Builds a usable array for an organisation
     * or user address, based on common associative keys.
     */
    static function build_address_array($e)
    {
        $city_town = (!empty($e['city_town'] )) ? ucwords(trim($e['city_town'])) : null;
        $city_town_full = $city_town;
        if(!is_null($city_town))
        {
            $postcode = (!empty($e['postcode'] )) ? strtoupper(trim($e['postcode'])) : null;
            if(!is_null($postcode))
                $city_town_full.= ' ' . $postcode;
        }

        // add city_town to filter duplicates, then remove
        $arr[] = $city_town;
        $count_a = count($arr);
        $arr = self::array_unique_ci($arr);
        $count_b = count($arr);
        if($count_a == $count_b)
            array_pop($arr);

        //re-add city_town, this time with postcode (also filter duplicates)
        if(!is_null($city_town_full))
        {
            $arr[] = $city_town_full;
            $arr = self::array_unique_ci($arr);
        }

        // add county, if wanted
        $county_code = (!empty( $e['county_code' ] )) ? $e['county_code' ] . ' ' : null;
        $county = (!empty($e['county'] )) ? (!empty($e['show_county'] )) ? ucwords(trim($e['county'])) : null : null;
        if( (!is_null($county)) && ( strtoupper($city_town) !== strtoupper($county) ) )
            $arr[] = $county_code . $county;

        // filter again
        $arr = self::array_unique_ci($arr);

        $eircode = (!empty($e['eircode'] )) ? strtoupper(trim($e['eircode'])) : null;
        if(!is_null($eircode))
            $arr[] = $eircode;

        return $arr;
    }

    static function build_local_address_str($arr, $max_length = null)
    {
        $arr = array_change_key_case($arr, CASE_LOWER);
        $a = [];

        if(!empty($arr['house_num']))
        {
            if(is_numeric($arr['house_num']))
                $arr['house_num'] = 'No. ' . $arr['house_num'];
            $a[] = trim($arr['house_num']);
        }
        if(!empty($arr['add_1']))
            $a[] = trim($arr['add_1']);
        if(!empty($arr['add_2']))
            $a[] = trim($arr['add_2']);

        $str = implode(', ', $a);
        if(!is_null($max_length))
            $str = self::shorten_str_to_length($str, $max_length);

        return $str;
    }

    static function build_short_address_str($arr, $max_length = null)
    {
        $arr = array_change_key_case($arr, CASE_LOWER);
        $a = [];

        if(!empty($arr['house_num']))
        {
            if(is_numeric($arr['house_num']))
                $arr['house_num'] = 'No. ' . $arr['house_num'];
            $a[] = trim($arr['house_num']);
        }
        if(!empty($arr['add_1']))
            $a[] = trim($arr['add_1']);
        if(!empty($arr['add_2']))
            $a[] = trim($arr['add_2']);
        if(!empty($arr['add_3']))
            $a[] = trim($arr['add_3']);
        if(!empty($arr['town_city']))
            $a[] = trim($arr['town_city']);

        $str = implode(', ', $a);
        if(!is_null($max_length))
            $str = self::shorten_str_to_length($str, $max_length);

        return $str;
    }

    static function build_complete_address_str($arr, $max_length = null)
    {
        $arr = array_change_key_case($arr, CASE_LOWER);
        $a = [];

        if(!empty($arr['letters_send_to']))
            $a[] = trim($arr['letters_send_to']);
        if(!empty($arr['house_num']))
        {
            if(is_numeric($arr['house_num']))
                $arr['house_num'] = 'No. ' . $arr['house_num'];
            $a[] = trim($arr['house_num']);
        }
        if(!empty($arr['add_1']))
            $a[] = trim($arr['add_1']);
        if(!empty($arr['add_2']))
            $a[] = trim($arr['add_2']);
        if(!empty($arr['add_3']))
            $a[] = trim($arr['add_3']);
        if(!empty($arr['city_town']))
            $a[] = trim($arr['city_town']);
        if( (!empty($arr['county'])) && (!empty($arr['show_county'])) )
            $a[] = trim($arr['county']);
        if( (!empty($arr['country'])) && (strtoupper($arr['country']) != 'IRELAND') )
            $a[] = trim($arr['country']);
        if(!empty($arr['eircode']))
            $a[] = trim($arr['eircode']);

        $str = implode(', ', $a);
        if(!is_null($max_length))
            $str = self::shorten_str_to_length($str, $max_length);

        return $str;
    }

    static function shorten_str_to_length($str, $max_length)
    {
        $max_length = intval($max_length);
        if(strlen($str) > $max_length)
        {
            $string = wordwrap($str, $max_length, "##", true);
            $arr = explode("##", $string);
            $str = rtrim(trim($arr[0]),',');
        }

        return $str;
    }

    /**
     * @param $arr
     * @return array
     *
     * Case-insensitive array_unique
     * Keeps first encountered value
     */
    static function array_unique_ci($arr)
    {
        $lowered = array_map('strtolower', $arr);
        return array_intersect_key($arr, array_unique($lowered));
    }

    /**
     * @param $dir_path
     * @param int $depth
     *
     * Finds and requires all files in a directory and
     * recursively in sub-directories (to a max depth)
     */
    static function require_all_from_dir($dir_path, $depth = 0)
    {
        if($depth > self::$recursion_max_depth)
            return;

        $scan = glob($dir_path . "*");

        foreach($scan as $path)
        {
            if(preg_match('/\.php$/', $path))
                require_once $path;
            elseif(is_dir($path))
                self::require_all_from_dir($path, $depth+1);
        }
    }

    static function check_for_fatal_errors()
    {
        $arr = ['build','fatal','permissions'];
        $have_fatal_error = false;
        foreach($arr as $severity)
        {
            $ex = Core::$screen_errors[$severity];
            if(!empty($ex))
                $have_fatal_error = true;
        }

        return $have_fatal_error;
    }

    /**
     * Take any format mobile number and return it in GSM International format
     * @param $num
     * @param $country_code_short
     * @return string
     */
    static function number_format_gsm($num, $country_code_short = null)
    {
        $country_code_short = (is_null($country_code_short)) ? 'IE' : strtoupper($country_code_short);
        try
        {
            $num = preg_replace('/\D\+/', '', $num);

            $included = Helper::require_libphonenumber();
            if($included !== true)
                throw new Exception($included);

            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();

            $numberPrototype = $phoneUtil->parse($num, $country_code_short);
            if(!$numberPrototype)
                throw new Exception('LibPhoneNumber could not create an instance.');

            $tel = $phoneUtil->format($numberPrototype, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
            if(!$tel)
                throw new Exception('LibPhoneNumber could not format this number.');

            return $tel;
        }
        catch(Exception $e)
        {
            return ['error' => $e->getMessage()];
        }
    }



    // pagination & num results
    static function build_num_results_ctn()
    {
        return '<p class="screen-filters-count">Num. Results: <span class="screen-filters-count-ctn"></span></p>';
    }

    static function build_pagination_ctn()
    {
        $h = '<ul class="pagination clearfix">';
        $h.= '<li class="direction first"><span class="fa fa-angle-double-left"></span>First</li>';
        $h.= '<li class="direction previous"><span class="fa fa-angle-left"></span>Previous</li>';
        $h.= '<li class="pages"><ul></ul></li>';
        $h.= '<li class="direction next">Next<span class="fa fa-angle-right"></span></li>';
        $h.= '<li class="direction last">Last<span class="fa fa-angle-double-right"></span></li>';
        $h.= '</ul>';

        return $h;
    }










    /**
     * Retrieve a set parameter as an ordered array.
     * Accepts an array of arguments:
     *
     * param - the identifying string of the (singular) parameter (required)
     *
     * where_col - name of table column to filter by
     * where_col_eq - the filter value to filter results by
     * filter_in - comma separated string of filter values to filter results by (superseded by filter_val)
     *
     * order_col - the column name to order results by first (must have order_val)
     * order_val - the order value to order by first (must have order_col)
     * order_by - a column name to order by default (must have order)
     * order - the default order: ASC or DESC (defaults to ASC) (must have order_by)
     *
     * offset - defaults to 0
     * limit - limit of results returned
     *
     * @param array $arr
     * @return array
     */
    static function get_parameter($arr = [])
    {
        try
        {
            if(empty($arr['param']))
                throw new Exception('No Parameter description set.');

            $param = strtolower( preg_replace('/\s/', '', $arr['param']) );
            $tbl = 'prm_' . $param;
            
            $db = new Database();
            
            //get column names from table
            $sql = " SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = '" . JCT_DB_SIUD_NAME . "' AND TABLE_NAME = '" . $tbl . "'; ";
            $db->query($sql);
            $db->execute();
            $table_columns = $db->fetchAllColumn();
            
            if(empty($table_columns))
                throw new Exception('A Table for this Parameter could not be found.');

            $filter_col = (!empty($arr['filter_col'])) ? strtolower( preg_replace('/\s/', '', $arr['filter_col']) ) : null;
            $filter_col = (in_array($filter_col, $table_columns)) ? $filter_col : null;
            $filter_val = (!empty($arr['filter_val'])) ? trim($arr['filter_val']) : null;
            $filter_in = (!empty($arr['filter_in'])) ? strtoupper( trim($arr['filter_in']) ) : null;
            $bound_filter_in_string = null;
            $bound_filter_in_arr = [];
            if(!is_null($filter_in))
            {
                $tmp = explode(',',$filter_in);
                $tmp = array_map('trim', $tmp);
                $bound_filter_in_string = Helper::pdo_bind_array('f',$tmp,$bound_filter_in_arr);
            }

            $order_col = (!empty($arr['order_col'])) ? strtolower( preg_replace('/\s/', '', $arr['order_col']) ) : null;
            $order_col = (in_array($order_col, $table_columns)) ? $order_col : null;
            $order_val = (!empty($arr['order_val'])) ? trim($arr['order_val']) : null;

            $order_by = (!empty($arr['order_by'])) ? strtolower( preg_replace('/\s/', '', $arr['order_by']) ) : null;
            $order_by = (in_array($order_by, $table_columns)) ? $order_by : null;
            $order = (!empty($arr['order'])) ? strtoupper( trim($arr['order']) ) : null;
            $order = (in_array($order, ['ASC', 'DESC'])) ? $order : null;
            if( (!is_null($order_by)) && (is_null($order)) )
                $order = 'ASC';
            
            $offset = (!empty($arr['offset'])) ? intval($arr['offset']) : 0;
            $limit = (!empty($arr['limit'])) ? intval($arr['limit']) : 0;

            $where = " WHERE 1";
            if( (!is_null($filter_col)) && ( (!is_null($filter_val)) || (!is_null($bound_filter_in_string)) ) )
            {
                $where = " WHERE ( UPPER({$filter_col}) ";
                if(!is_null($filter_val))
                    $where.= "= UPPER(:filter_val) )";
                else if(!is_null($bound_filter_in_string))
                    $where.= "IN ({$bound_filter_in_string}) )";
            }

            $sql = " SELECT * FROM " . $tbl . $where;

            if( ( (!is_null($order_col)) && (!is_null($order_val)) ) || ( (!is_null($order_by)) && (!is_null($order)) ) )
            {
                $str = " ORDER BY";

                $orders = [];
                if( (!is_null($order_col)) && (!is_null($order_val)) )
                    $orders[] = " ( UPPER({$order_col}) = UPPER(:order_val) ) DESC";

                if( (!is_null($order_by)) && (!is_null($order)) )
                    $orders[] = " {$order_by} " . $order;
                
                $orders_str = implode(',', $orders);
                $str.= $orders_str;
                $sql.= $str;
            }
            
            if(!empty($limit))
                $sql.= " LIMIT " . $offset . "," . $limit;

            $db->query($sql);
            if(!is_null($filter_col))
            {
                if(!is_null($filter_val))
                    $db->bind(':filter_val', $filter_val);
                else if( (!is_null($bound_filter_in_arr)) && (!is_null($bound_filter_in_string)) )
                {
                    foreach($bound_filter_in_arr as $f => $v)
                        $db->bind($f, $v);
                }
            }
            if( (!is_null($order_col)) && (!is_null($order_val)) )
                $db->bind(':order_val', $order_val);
            $db->execute();
            $tmp = $db->fetchAllAssoc();
            
            return ['success'=>'ok', 'response'=>$tmp];
        }
        catch(Exception $e)
        {
            return ['error'=>'Parameter not returned: ' . $e->getMessage()];
        }
    }

    /**
     * When using arrays in PDO IN(...) query,
     * returns a set of bound tokens as a comma separated string
     * while updating the submitted (empty) $bound_array to token => value pairs
     * @param $prefix
     * @param $values
     * @param $bound_array
     * @return string
     */
    static function pdo_bind_array($prefix, $values, &$bound_array)
    {
        $str = "";
        foreach($values as $i => $v)
        {
            $str .= ":".$prefix.$i.",";
            $bound_array[$prefix.$i] = $v;
        }
        return rtrim($str,",");
    }

    static function excel_to_array($path_to_file, $use_first_row = false)
    {
        try
        {
            if(is_readable(JCT_PATH_CORE_VENDORS . 'phpexcel' . JCT_DE . 'PHPExcel.php'))
                require_once JCT_PATH_CORE_VENDORS . 'phpexcel' . JCT_DE . 'PHPExcel.php';
            else
                throw new Exception('PHPExcel init file not found.');
            \PHPExcel_Settings::setZipClass(\PHPExcel_Settings::PCLZIP);

            $excel_obj = \PHPExcel_IOFactory::load($path_to_file);
            $tmp = $excel_obj->getActiveSheet()->toArray(null,true,true,true);

            if(!$use_first_row)
                return ['success'=>'ok', 'response'=>$tmp];

            $fields = [];
            $sheet_data = [];
            foreach($tmp as $i => $t)
            {
                if(empty($fields))
                {
                    foreach($t as $n => $f)
                    {
                        if(!empty($f))
                            $fields[] = $f;
                    }

                    break;
                }
            }
            $num_fields = count($fields) - 1;

            $rm = array_shift($tmp);
            foreach($tmp as $i => $t)
            {
                $x = 0;
                foreach($t as $n => $f)
                {
                    $sheet_data[$i][ $fields[$x] ] = $f;
                    if($x >= $num_fields)
                        break;
                    $x++;
                }
            }

            return $sheet_data;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    static function requireLibPhonenumberFiles()
    {
        foreach (scandir(JCT_PATH_CORE_VENDORS . 'libphonenumber') as $filename)
        {
            if(in_array($filename, ['.','..']))
                continue;
            if(strpos($filename, 'Interface') === false)
                continue;

            $path = JCT_PATH_CORE_VENDORS . 'libphonenumber' . JCT_DE . $filename;
            if (is_file($path))
                require_once $path;
        }

        foreach (scandir(JCT_PATH_CORE_VENDORS . 'libphonenumber') as $filename)
        {
            if(in_array($filename, ['.','..']))
                continue;

            $path = JCT_PATH_CORE_VENDORS . 'libphonenumber' . JCT_DE . $filename;
            if (is_file($path))
                require_once $path;
        }

        return true;
    }

    static $plural = array(
        '/(quiz)$/i'               => "$1zes",
        '/^(ox)$/i'                => "$1en",
        '/([m|l])ouse$/i'          => "$1ice",
        '/(matr|vert|ind)ix|ex$/i' => "$1ices",
        '/(x|ch|ss|sh)$/i'         => "$1es",
        '/([^aeiouy]|qu)y$/i'      => "$1ies",
        '/(hive)$/i'               => "$1s",
        '/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
        '/(shea|lea|loa|thie)f$/i' => "$1ves",
        '/sis$/i'                  => "ses",
        '/([ti])um$/i'             => "$1a",
        '/(tomat|potat|ech|her|vet)o$/i'=> "$1oes",
        '/(bu)s$/i'                => "$1ses",
        '/(alias)$/i'              => "$1es",
        '/(octop)us$/i'            => "$1i",
        '/(ax|test)is$/i'          => "$1es",
        '/(us)$/i'                 => "$1es",
        '/s$/i'                    => "s",
        '/$/'                      => "s"
    );

    static $singular = array(
        '/(quiz)zes$/i'             => "$1",
        '/(matr)ices$/i'            => "$1ix",
        '/(vert|ind)ices$/i'        => "$1ex",
        '/^(ox)en$/i'               => "$1",
        '/(alias)es$/i'             => "$1",
        '/(octop|vir)i$/i'          => "$1us",
        '/(cris|ax|test)es$/i'      => "$1is",
        '/(shoe)s$/i'               => "$1",
        '/(o)es$/i'                 => "$1",
        '/(bus)es$/i'               => "$1",
        '/([m|l])ice$/i'            => "$1ouse",
        '/(x|ch|ss|sh)es$/i'        => "$1",
        '/(m)ovies$/i'              => "$1ovie",
        '/(s)eries$/i'              => "$1eries",
        '/([^aeiouy]|qu)ies$/i'     => "$1y",
        '/([lr])ves$/i'             => "$1f",
        '/(tive)s$/i'               => "$1",
        '/(hive)s$/i'               => "$1",
        '/(li|wi|kni)ves$/i'        => "$1fe",
        '/(shea|loa|lea|thie)ves$/i'=> "$1f",
        '/(^analy)ses$/i'           => "$1sis",
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'  => "$1$2sis",
        '/([ti])a$/i'               => "$1um",
        '/(n)ews$/i'                => "$1ews",
        '/(h|bl)ouses$/i'           => "$1ouse",
        '/(corpse)s$/i'             => "$1",
        '/(us)es$/i'                => "$1",
        '/s$/i'                     => ""
    );

    static $irregular = array(
        'move'   => 'moves',
        'foot'   => 'feet',
        'goose'  => 'geese',
        'sex'    => 'sexes',
        'child'  => 'children',
        'man'    => 'men',
        'tooth'  => 'teeth',
        'person' => 'people',
        'valve'  => 'valves'
    );

    static $uncountable = array(
        'sheep',
        'fish',
        'deer',
        'series',
        'species',
        'money',
        'rice',
        'information',
        'equipment'
    );

    public static function pluralize( $string )
    {
        // save some time in the case that singular and plural are the same
        if ( in_array( strtolower( $string ), self::$uncountable ) )
            return $string;


        // check for irregular singular forms
        foreach ( self::$irregular as $pattern => $result )
        {
            $pattern = '/' . $pattern . '$/i';

            if ( preg_match( $pattern, $string ) )
                return preg_replace( $pattern, $result, $string);
        }

        // check for matches using regular expressions
        foreach ( self::$plural as $pattern => $result )
        {
            if ( preg_match( $pattern, $string ) )
                return preg_replace( $pattern, $result, $string );
        }

        return $string;
    }

    public static function singularize( $string )
    {
        // save some time in the case that singular and plural are the same
        if ( in_array( strtolower( $string ), self::$uncountable ) )
            return $string;

        // check for irregular plural forms
        foreach ( self::$irregular as $result => $pattern )
        {
            $pattern = '/' . $pattern . '$/i';

            if ( preg_match( $pattern, $string ) )
                return preg_replace( $pattern, $result, $string);
        }

        // check for matches using regular expressions
        foreach ( self::$singular as $pattern => $result )
        {
            if ( preg_match( $pattern, $string ) )
                return preg_replace( $pattern, $result, $string );
        }

        return $string;
    }

    public static function pluralize_if($count, $string)
    {
        if ($count == 1)
            return "1 $string";
        else
            return $count . " " . self::pluralize($string);
    }

}