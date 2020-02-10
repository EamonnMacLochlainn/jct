<?php


namespace JCT;



use Exception;

class RouteRegistry
{

    /**
     * @var array
     *
     * Where the app_slug is a key to its properties, those being
     * @title an associative array, using language codes as keys (e.g. 'en_GB')
     * pointing to strings to use in Navigation
     * @modules an associative array, using module slugs as keys ('none' if without modules)
     */

    private static $apps = [

        'site' => [
            'titles' => [],
            'requires_login' => false, // whether or not the app requires a User to be logged in
            'is_required_for_all_users' => true, // whether or not all logged in users have access to it
            'is_modular' => false, // whether or not the app is sub-divided into modules
            'has_internal_navigation' => false, // whether or not the app has its own navigation menu
            'icon' => null, // the FA icon class used for this app's icon
            'is_functional' => true,  // whether or not the app actually works yet,
            'for_org_types' => [-1], // the org types that may access by default,
            'for_role_ids' => [-1], // the role IDs that may access by default
            'modules' => [
                'none' => [
                    'titles' => [],
                    'destinations' => [
                        'home' => [
                            'titles'=>['en_GB'=>'Home','ga_IE'=>'Home'],
                            'model'=>'Home',
                            'method'=>'index',
                            'show_in_nav' => false
                        ],
                        'privacy' => [
                            'titles'=>['en_GB'=>'Privacy','ga_IE'=>'Privacy'],
                            'model'=>'Privacy',
                            'method'=>'index',
                            'show_in_nav' => false
                        ],
                        'contact' => [
                            'titles'=>['en_GB'=>'Contact','ga_IE'=>'Contact'],
                            'model'=>'Contact',
                            'method'=>'index',
                            'show_in_nav' => false
                        ],
                        'help' => [
                            'titles'=>['en_GB'=>'Help','ga_IE'=>'Help'],
                            'model'=>'Help',
                            'method'=>'index',
                            'show_in_nav' => false
                        ],
                        'login' => [
                            'titles'=>['en_GB'=>'Login','ga_IE'=>'Login'],
                            'model'=>'Home',
                            'method'=>'index',
                            'show_in_nav' => false
                        ],
                        'logout' => [
                            'titles'=>['en_GB'=>'Logout','ga_IE'=>'Logout'],
                            'model'=>'Home',
                            'method'=>'logout',
                            'show_in_nav' => false
                        ],
                        'error' => [
                            'titles'=>['en_GB'=>'Error','ga_IE'=>'Error'],
                            'model'=>'Error',
                            'method'=>'index',
                            'show_in_nav' => false
                        ]
                    ],
                    'destination_aliases' => ['contact-us'=>'contact','contactus'=>'contact']
                ]
            ]
        ],
        'dashboard' => [
            'titles' => ['en_GB'=>'Dashboard','ga_IE'=>'Dashboard'],
            'requires_login' => true,
            'is_required_for_all_users' => true,
            'is_modular' => false,
            'has_internal_navigation' => false,
            'icon' => null,
            'is_functional' => true,
            'for_org_types' => [-1],
            'for_role_ids' => [-1],
            'modules' => [
                'none' => [
                    'titles' => [],
                    'destinations' => [
                        'home' => [
                            'titles'=>['en_GB'=>'Dashboard','ga_IE'=>'Dashboard'],
                            'model'=>'Home',
                            'method'=>'index',
                            'show_in_nav' => false
                        ]
                    ],
                    'destination_aliases' => []
                ]
            ]
        ]
    ];


    function __construct()
    {
    }

    public static function get_app($app_slug, $module_slug = null)
    {
        try
        {
            if(!array_key_exists($app_slug, self::$apps))
                throw new Exception('App slug not found in Route Registry.');

            $app = self::$apps[$app_slug];

            $module_slug = ($module_slug !== null) ? Helper::strip_all_white_space($module_slug) : null;

            if($module_slug === null)
                return $app;

            $module_slug = (empty($module_slug)) ? Router::DEFAULT_NON_MODULAR_MODULE_SLUG : $module_slug;

            if(!array_key_exists($module_slug, $app['modules']))
                throw new Exception('Module slug not found in Route Registry.');

            foreach($app['modules'] as $m_slug => $m)
            {
                if($m_slug !== $module_slug)
                    unset($app['modules'][$m_slug]);
            }

            return $app;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    public static function get_app_properties($app_slug)
    {
        try
        {
            if(!array_key_exists($app_slug, self::$apps))
                throw new Exception('App slug not found in Route Registry.');

            $app = self::$apps[$app_slug];
            unset($app['modules']);

            return $app;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    public static function get_route($app_slug, $model_slug, $module_slug = null)
    {
        try
        {
            if(!array_key_exists($app_slug, self::$apps))
                throw new Exception('App slug not found in Route Registry.');

            $module_slug = ($module_slug === null) ? Router::DEFAULT_NON_MODULAR_MODULE_SLUG : $module_slug;

            if(!array_key_exists($module_slug, self::$apps[$app_slug]['modules']))
                throw new Exception('Module slug not found in Route Registry.');

            if(!array_key_exists($model_slug, self::$apps[$app_slug]['modules'][$module_slug]['destinations']))
            {
                if(!array_key_exists($model_slug, self::$apps[$app_slug]['modules'][$module_slug]['destination_aliases']))
                    throw new Exception('Model slug not found in Route Registry.');
                else
                    $model_slug = self::$apps[$app_slug]['modules'][$module_slug]['destination_aliases'][$model_slug];
            }

            $route = [];
            $route['app_slug'] = $app_slug;
            $route['module_slug'] = $module_slug;
            $route['model_slug'] = $model_slug;
            $route['model_filename'] = self::$apps[$app_slug]['modules'][$module_slug]['destinations'][$model_slug]['model'];
            $route['method'] = self::$apps[$app_slug]['modules'][$module_slug]['destinations'][$model_slug]['method'];
            $route['app_titles'] = self::$apps[$app_slug]['titles'];
            $route['module_titles'] = self::$apps[$app_slug]['modules'][$module_slug]['titles'];
            $route['model_titles'] = self::$apps[$app_slug]['modules'][$module_slug]['destinations'][$model_slug]['titles'];

            return $route;
        }
        catch(Exception $e)
        {
            return ['error'=>$e->getMessage()];
        }
    }

    public static function get_app_slugs()
    {
        return array_keys(self::$apps);
    }

    public static function get_apps_by_filter($filter)
    {
        $resp = [];

        foreach(self::$apps as $app_slug => $app)
        {
            foreach($filter as $key => $value)
            {
                if(!isset($app[$key]))
                    return false;

                if($app[$key] !== $value)
                    continue 2;
            }

            $resp[$app_slug] = $app;
        }

        return $resp;
    }
}