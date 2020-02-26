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
            'is_functional' => true,  // whether or not the app actually works yet
            'requires_login' => false, // whether or not the app requires a User to be logged in
            'has_internal_navigation' => false, // whether or not the app has its own navigation menu
            'icon' => null, // the FA icon class used for this app's icon
            'accessed_per_org_type' => false, // whether or not this app is directly accessed, or accessed by org type
            'accessed_per_role' => false, // whether or not this app has just one module (all), or modules accessed by role
            'titles' => ['en_GB'=>'Site','ga_IE'=>'Site'],
            'org_sections' => [
                'all' => [
                    'type_ids' => [],
                    'titles' => ['en_GB'=>'Site','ga_IE'=>'Site'],
                    'user_modules' => [
                        'all' => [
                            'role_ids' => [],
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
                ]
            ]
        ],
        'dashboard' => [
            'is_functional' => true,
            'requires_login' => true,
            'has_internal_navigation' => false,
            'icon' => null,
            'accessed_per_org_type' => true,
            'accessed_per_role' => true,
            'titles' => ['en_GB'=>'Dashboard','ga_IE'=>'Dashboard'],
            'org_sections' => [
                'jct' => [
                    'type_ids' => [],
                    'titles' => ['en_GB'=>'Dashboard','ga_IE'=>'Dashboard'],
                    'user_modules' => [
                        'admin' => [
                            'role_ids' => [1,2],
                            'destinations' => [
                                'home' => [
                                    'titles'=>['en_GB'=>'Home','ga_IE'=>'Home'],
                                    'model'=>'Home',
                                    'method'=>'index',
                                    'show_in_nav' => false
                                ]
                            ],
                            'destination_aliases' => []
                        ],
                        'team_leader' => [
                            'role_ids' => [],
                            'destinations' => [],
                            'destination_aliases' => []
                        ],
                        'associate' => [
                            'role_ids' => [],
                            'destinations' => [],
                            'destination_aliases' => []
                        ]
                    ]
                ],
                'school' => [
                    'type_ids' => [],
                    'titles' => ['en_GB'=>'Dashboard','ga_IE'=>'Dashboard'],
                    'user_modules' => [
                        'admin' => [
                            'role_ids' => [],
                            'destinations' => [],
                            'destination_aliases' => []
                        ],
                        'cpd_coordinator' => [
                            'role_ids' => [],
                            'destinations' => [],
                            'destination_aliases' => []
                        ]
                    ]
                ]
            ]
        ]
    ];


    function __construct()
    {
    }

    public static function get_route_properties($app_slug, $org_section_slug = null, $user_module_slug = null)
    {
        try
        {
            if(!array_key_exists($app_slug, self::$apps))
                throw new Exception('App slug not found in Route Registry.');

            $org_section_slug = ($org_section_slug !== null) ? Helper::strip_all_white_space($org_section_slug) : null;
            $user_module_slug = ($user_module_slug !== null) ? Helper::strip_all_white_space($user_module_slug) : null;

            // if section not specified, return app
            $app = self::$apps[$app_slug];
            if($org_section_slug == null)
                return $app;

            // if section is specified, remove all others
            if(!array_key_exists($org_section_slug, $app['org_sections']))
                throw new Exception('Section slug not found in Route Registry.');

            foreach($app['org_sections'] as $slug => $section)
            {
                if($slug !== $org_section_slug)
                    unset($app['org_sections'][$slug]);
            }

            // if module not specified, return app
            if($user_module_slug === null)
                return $app;

            // if module is specified, remove all others
            if(!array_key_exists($user_module_slug, $app['org_sections'][$org_section_slug]['user_modules']))
                throw new Exception('Module slug not found in Route Registry.');

            foreach($app['org_sections'][$org_section_slug]['user_modules'] as $module_slug => $module)
            {
                if($module_slug !== $user_module_slug)
                    unset($app['org_sections'][$org_section_slug]['user_modules'][$module_slug]);
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

            $module_slug = ($module_slug === null) ? Router::DEFAULT_USER_MODULE_SLUG : $module_slug;

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