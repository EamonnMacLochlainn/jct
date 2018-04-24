<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 17/07/2017
 * Time: 14:58
 */

namespace JCT;


class Localisation
{
    private static $default_country_code = 'IE';

    private $locales = [
        'en_GB',
        'ga_IE'
    ];
    private $default_locale = 'en_GB';
    private static $messages;

    function __construct()
    {

    }

    function valid_locale($locale = null)
    {
        return (in_array($locale, $this->locales));
    }

    static function __($key)
    {
        $loc = new Localisation();
        $tmp = (!empty($_SESSION['locale'])) ? $_SESSION['locale'] : null;

        $locale = ($loc->valid_locale($tmp)) ? $tmp : $loc->default_locale;

        $path = JCT_PATH_CORE . 'localisation' . JCT_DE . $locale . '.php';
        if(!is_readable($path))
            return $key;

        if(empty(self::$messages))
        {
            include $path;
            if(empty($_MSG))
                return $key;
            self::$messages = $_MSG;
        }

        $key_upper = strtoupper($key);
        if(!isset(self::$messages[$key_upper]))
            return $key;

        return self::$messages[$key_upper];
    }

    static function require_libphonenumber()
    {
        $root = JCT_PATH_CORE_VENDORS . 'libphonenumber' . JCT_DE;

        require_once $root . 'MatcherAPIInterface.php';
        require_once $root . 'MetadataLoaderInterface.php';
        require_once $root . 'MetadataSourceInterface.php';
        require_once $root . 'CountryCodeToRegionCodeMap.php';
        require_once $root . 'DefaultMetadataLoader.php';
        require_once $root . 'MultiFileMetadataSourceImpl.php';
        require_once $root . 'PhoneNumber.php';
        require_once $root . 'Matcher.php';
        require_once $root . 'PhoneMetadata.php';
        require_once $root . 'PhoneNumberDesc.php';
        require_once $root . 'NumberFormat.php';
        require_once $root . 'CountryCodeSource.php';
        require_once $root . 'ValidationResult.php';
        require_once $root . 'PhoneNumberType.php';
        require_once $root . 'PhoneNumberUtil.php';
        require_once $root . 'PhoneNumberFormat.php';

        return true;
    }

    static function parse_contact_number($country_code = null, $number)
    {
        $number = preg_replace("/[^0-9+]/", "", $number);
        if(empty($number))
            return null;

        $country_code = (is_null($country_code)) ? self::$default_country_code : $country_code;
        $country_code = strtoupper(trim($country_code));

        try
        {
            self::require_libphonenumber();
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();

            $numberPrototype = $phoneUtil->parse($number, $country_code);
            if(!$phoneUtil->isValidNumber($numberPrototype))
                return ['error'=>'This number does not appear to be valid.'];

            return $phoneUtil->format($numberPrototype, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);

        }
        catch(\Exception $e)
        {
            return null;
        }
    }
}