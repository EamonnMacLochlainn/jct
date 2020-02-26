<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:48
 */

namespace JCT;



class Render extends Router
{
    private $view;

    private $content_security = [
        'default-src' => [
            '&apos;self&apos;',
            '&apos;unsafe-inline&apos;',
            'https://www.google.com/',
            'https://google.com',
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/',
            'blob:'
        ],
        'script-src' => [
            '&apos;self&apos;',
            '&apos;unsafe-inline&apos;',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/',
            'https://www.google.com/',
            'https://www.gstatic.com/',
            'https://www.google.com/recaptcha/api.js'
        ],
        'object-src' => [
            '&apos;none&apos;',
        ],
        'media-src' => [
            '&apos;self&apos;',
            'blob:'
        ]
    ];


    function __construct()
    {
        ob_start();

        parent::__construct();
        if(!$this->default_connection_ok)
            die();

        // generate screen data
        $method_title = $this->method_title;
        $method_data = $this->model->$method_title($this->method_arguments);

        $view_title = $this->model_title . 'View';
        $view_class_name = substr($this->model_class_name, 0, -5) . 'View';
        $this->view = new $view_class_name($this->model);

        // generate screen content
        $this->view->$method_title();
        $this->build_view();

        echo ob_get_clean();
    }

    function build_view()
    {
        $app_nav = $this->get_app_navigation();
        $app_nav_class = (!empty($app_nav)) ? 'with-app-nav' : 'without-app-nav';


        // open doc
        $h = '<!DOCTYPE html><html lang="en">';
        $h.= $this->build_header();

        // open body, screen wrap
        $h.= '<body class="' . $this->build_view_classes() . '">';
        $h.= '<div class="screen-wrap ' . $app_nav_class . ' clearfix">';
        $h.= '<a name="top"></a>';

        $h.= $app_nav;

        // view header
        $h.= '<header data-role="header" class="screen-header ' . $app_nav_class . '">';
        $h.= $this->build_global_nav_html();
        $h.= '</header>';

        // content
        $h.= '<section data-role="page" class="screen-content ' . $app_nav_class . '">';
        if(!empty($this->view->screen_title))
            $h.= '<h1 class="screen-title">' . $this->view->screen_title . '</h1>';
        if(!empty($this->view->screen_blurb))
            $h.= '<p class="screen-blurb">' . $this->view->screen_blurb . '</p>';
        $h.= $this->view->screen_content . '</section>';


        // append slide window
        $h.= '<div class="slide-window">';
        $h.= '<div class="slide-window-controls">';
        $h.= '<span class="slide-window-retract">Close <i class="fal fa-chevron-double-right"></i></span>';
        $h.= '</div>';
        $h.= '<div class="header"></div><div class="inner"></div></div>';

        // close screen wrap
        $h.= '</div>';
        $h.= '<a href="#top" class="back-to-top fa-stack"><i class="fa fa-circle fa-stack-2x"></i><i class="fa fa-chevron-up fa-stack-1x fa-inverse"></i></a>';

        // view footer
        $h.= $this->build_footer();

        // include JS scripts
        $h.= $this->build_view_scripts();

        // close body, doc
        $h.= '</body>';
        $h.= '</html>';

        echo $h;
    }


    // HEADER functions

    private function build_header()
    {
        $h = '<head>';

        $h.= $this->build_meta();
        $h.= $this->build_view_stylesheets();
        $h.= '<title>' . $this->build_tab_title() . '</title>';

        // favicon links
        $h.= <<<HTML
<link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192"  href="/android-icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/manifest.json">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
HTML;

        // recaptcha dependent scripts
        if(in_array('recaptcha',$this->view->screen_classes))
            $h.= '<script src="https://www.google.com/recaptcha/api.js"></script>';

        $h.= '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat|Open+Sans:400,800&subset=latin-ext" />';

        $h.= '</head>';

        return $h;
    }

    private function build_meta()
    {
        // does not include favicon TileColor/Image meta tags which are included with favicon links

        $csrf = session_id();
        $locale = ($this->user_is_logged_in) ? $this->user_locale : 'en_GB';

        $h = '<meta charset="utf-8">';

        $h.= '<meta http-equiv="x-ua-compatible" content="ie=edge">';
        $h.= '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=1">';
        $h.= '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">';
        $h.= '<meta name="csrf" content="' . $csrf . '">';
        $h.= '<meta name="locale" content="' . $locale . '">';
        $h.= '<meta name="theme-color" content="#27873D">';
        $h.= '<meta name="_inst_dir" content="' . JCT_INSTALLATION_DIR . '">';

        $csp_str = 'default-src ';
        foreach($this->content_security['default-src'] as $src)
            $csp_str.= $src . ' ';
        $csp_str.= '; script-src ';
        foreach($this->content_security['script-src'] as $src)
            $csp_str.= $src . ' ';
        $csp_str.= '; object-src ';
        foreach($this->content_security['object-src'] as $src)
            $csp_str.= $src . ' ';
        $csp_str.= '; media-src ';
        foreach($this->content_security['media-src'] as $src)
            $csp_str.= $src . ' ';

        $h.= '<meta http-equiv="Content-Security-Policy" content="' . trim($csp_str) . ';">';

        if(!empty($this->view->meta_description))
            $h.= '<meta name="description" content="' . $this->view->meta_description . '"/>';

        if(!$this->view->meta_robots_follow)
            $h.= '<meta name="robots" content="noindex"/>';

        return $h;
    }

    private function build_view_stylesheets()
    {
        // reset
        $h = '<link rel="stylesheet" href="' . JCT_URL_APPS . 'assets/css/normalize.css" />';

        // required vendor styles
        $h.= '<link rel="stylesheet" href="' . JCT_URL_APPS . 'assets/fonts/fontawesome/fontawesome-all.min.css" />';
        $h.= '<link rel="stylesheet" href="' . JCT_URL_APPS . 'assets/js/jquery-ui-1.12.1.custom/jquery-ui.min.css" />';

        // main platform styles
        $h.= '<link rel="stylesheet" href="' . JCT_URL_APPS . 'assets/css/main.css" />';

        // app styles
        if(is_readable(JCT_PATH_APPS . $this->view->app_param . JCT_DE . 'assets' . JCT_DE . 'css' . JCT_DE . 'style.css'))
            $h.= '<link rel="stylesheet" href="' . JCT_URL_APPS . $this->view->app_param . '/assets/css/style.css" />';

        // view styles
        if(!empty($this->view->view_stylesheets))
        {
            foreach($this->view->view_stylesheets as $stylesheet)
            {
                $prefix = strtolower(substr($stylesheet, 0, 4));

                // differentiate between external, core, and specific sheets
                switch($prefix)
                {
                    case('http'):
                        $h.= '<link rel="stylesheet" href="' . $stylesheet . '" />';
                        break;
                    case('core'):
                        $split = explode('/', $stylesheet);
                        $filename = array_pop($split);
                        $path = JCT_PATH_APPS . 'assets' . JCT_DE . 'css' . JCT_DE . $filename . '.css';
                        if(is_readable($path))
                            $h.= '<link rel="stylesheet" href="' . JCT_URL_APPS . 'assets/css/' . $filename . '.css" />';
                        break;
                    default:
                        $path = JCT_PATH_APPS . $this->view->app_param . JCT_DE . 'assets' . JCT_DE . 'css' . JCT_DE . $stylesheet . '.css';
                        if(is_readable($path))
                            $h.= '<link rel="stylesheet" href="' . JCT_URL_APPS . $this->view->app_param  . '/assets/css/' . $stylesheet . '.css" />';
                        break;
                }
            }
        }

        return $h;
    }

    private function build_tab_title()
    {
        $tab_titles = [];
        $tab_titles[] = 'JCT Registration';

        // get app_param
        // note that app_param may include module slug, so split that off
        $delimiter = (strpos($this->view->app_param, '/') === false) ? '\\' : '/';
        $split = explode($delimiter, $this->view->app_param);
        $app_param = array_shift($split);

        $locale = (!empty($_SESSION[SessionManager::SESSION_NAME]['user']['prefs'])) ? $_SESSION[SessionManager::SESSION_NAME]['user']['prefs']['locale'] : 'en_GB';
        $app = RouteRegistry::get_route_properties($app_param);

        if(!empty($app['titles'][$locale]))
            $tab_titles[] = $app['titles'][$locale];

        $view_tab_title = (!empty($this->view->screen_tab_title)) ? $this->view->screen_tab_title : null;
        $default_title = (!empty($app['titles']['en_GB'])) ? $app['titles']['en_GB'] : '';
        if( ($view_tab_title !== null) && ($view_tab_title !== $default_title) )
            $tab_titles[] = $view_tab_title;

        $tab_titles = array_reverse($tab_titles);

        return implode(' | ', $tab_titles);
    }

    private function build_view_classes()
    {
        // ensure basic class
        $delimiter = (strpos($this->view->app_param, '/') === false) ? '\\' : '/';
        $split = explode($delimiter, $this->view->app_param);
        $app_class = implode(' ', $split);
        array_unshift($this->view->screen_classes, $app_class);

        if(empty($this->view->screen_classes))
            $this->view->screen_classes[] = 'default';

        // add browser class
        $browser = \Browser::Browser();
        $this->view->screen_classes[] = Helper::slugify($browser);

        return implode(' ', $this->view->screen_classes);
    }


    // NAV functions (global)

    private function build_global_nav_html()
    {
        $h = '<nav class="global-nav">';
        $h.= '<span class="global-nav-trigger"><i class="fa fa-bars"></i></span>';
        $h.= '<ul>';

        $h.= '<li class="home-link"><a href="' . JCT_URL_ROOT . '"><i class="fa fa-home icon-left"></i><span>' . Localisation::__('JCT Registration') . '</span></a></li>';
        $h.= '<li class="jct-link"><a href="https://jct.ie">JCT.ie</a></li>';
        $h.= '<li class="feedback-link"><a href="' . JCT_URL_ROOT . 'Feedback">Feedback</a></li>';

        // legacy links vs new platform
        if($this->app_slug == 'site')
        {
            if($this->user_is_logged_in)
                $h.= '<li class="dashboard-link"><a href="' . JCT_URL_ROOT . 'Dashboard/"><i class="fa fa-cubes icon-left"></i><span>' . Localisation::__('DASHBOARD') . '</span></a></li>';
        }
        else
        {
            if($this->user_is_logged_in)
            {
                $log_guid = $_SESSION[SessionManager::SESSION_NAME]['org']['guid'];

                if($this->user_role_id === 1)
                {
                    $orgs = $this->get_org_options_for_sys_admin($this->default_db_connection);
                    if(count($orgs) > 0)
                    {
                        $options = '<option value="DATABIZ">Sys. Admin</option><option value="0">--</option>';
                        foreach($orgs as $guid => $org_name)
                        {
                            $selected = ($guid == $log_guid) ? ' selected' : '';
                            $options.= '<option value="' . $guid . '" ' . $selected . ' >' . $org_name .  ' (' . $guid . ')</option>';
                        }

                        $h.= '<li class="sysadmin-user"><select id="sysadmin-user-sel">' . $options . '</select></li>';
                    }
                }

                $h.= '<li class="user-link"><a href="' . JCT_URL_ROOT . 'User/"><i class="fa fa-user icon-left"></i><span>' . Localisation::__('USER') . '</span></a></li>';
                $h.= '<li class="dashboard-link"><a href="' . JCT_URL_ROOT . 'Dashboard/"><i class="fa fa-cubes icon-left"></i><span>' . Localisation::__('DASHBOARD') . '</span></a></li>';
                $h.= '<li><a href="' . JCT_URL_ROOT . 'Logout" class="login-link"><i class="fal fa-sign-out icon-left"></i><span>' . Localisation::__('LOG_OUT') . '</span></a></li>';
            }
        }




        $h.= '</ul>';
        $h.= '</nav>';

        if(($this->app_slug != 'site') && ($this->app_slug != 'dashboard'))
            $h.= '<div class="app-title-bar">' . $this->route_properties['titles'][$this->user_locale] . '</div>';

        return $h;
    }

    private function get_org_options_for_sys_admin(Database $db)
    {
        $db->query(" SELECT guid, title  
        FROM org 
        WHERE ( type_id = 3 ) 
        ORDER BY title ");
        $db->execute();
        return $db->fetchAllAssoc('guid', true);
    }


    // NAV functions (app)

    private function get_app_navigation()
    {
        if ($this->user_is_logged_in === false)
            return null;

        $nav = $this->get_navigation();
        if (empty($nav))
            return null;


        // build nav
        $ctn = '<nav class="app-nav-ctn ' . $this->app_slug . '-nav">';

        // home link
        $ctn .= '<div class="app-nav-home-link"><a href="' . JCT_URL_ROOT . '"><i class="fa fa-home icon-left"></i><span>DataBiz Solutions</span></a></div>';

        // sidebar
        $ctn .= '<div class="app-nav-sidebar">';
        $ctn .= '<span class="fa fa-bars app-nav-trigger" title="Menu"></span>';
        $ctn .= '<a href="' . JCT_URL_ROOT . 'Knowledgebase" class="knowledgebase-trigger"><i class="fa fa-info" title="Knowledgebase"></i></a>';
        $ctn .= '<a href="' . JCT_URL_ROOT . 'Dashboard" class="dashboard-trigger"><i class="fa fa-cubes" title="Dashboard"></i></a>';

        if ($this->user_role_id < 3)
            $ctn .= '<a href="' . JCT_URL_ROOT . 'Manager" class="manager-trigger"><i class="fa fa-address-card" title="Manager"></i></a>';

        $ctn .= '</div>';

        // nav window
        $ctn .= '<div class="app-nav-window">';
        // title
        $title = (!isset($this->route_properties['titles'][$this->user_locale])) ? '' : $this->route_properties['titles'][$this->user_locale];
        $ctn .= '<a class="app-nav-app-home" href="' . JCT_URL_ROOT . $this->app_slug . '">' . $title . '</a>';
        // nav
        $ctn .= $nav;
        $ctn .= '</div>';

        $ctn .= '</nav>';

        return $ctn;
    }

    private function get_navigation()
    {
        return false;

        $accessed_per_org_type = $this->route_properties['accessed_per_org_type'];
        $accessed_per_role = $this->route_properties['accessed_per_role'];

        //Helper::show($this->route_properties);

        $module_slug = ($this->route_properties['is_modular']) ? parent::DEFAULT_MODULAR_MODULE_SLUG : parent::DEFAULT_USER_MODULE_SLUG;

        // show nothing if application has no internal navigation

        if ($this->route_properties['has_internal_navigation'] === false)
            return null;


        // get destination options

        if ($this->user_role_id < 3) // dev or admin
        {
            foreach ($this->route_properties['modules'] as $module_slug => $m)
            {
                $title = (!isset($this->route_properties['modules'][$module_slug]['titles'][$this->user_locale])) ? '' : $this->route_properties['modules'][$module_slug]['titles'][$this->user_locale];
                $nav_map[$module_slug] = [
                    'title' => $title,
                    'destinations' => []
                ];

                foreach($m['destinations'] as $destination_slug => $d)
                {
                    if($this->route_properties['modules'][$module_slug]['destinations'][$destination_slug]['show_in_nav'] === false)
                        continue;

                    $title = (!isset($this->route_properties['modules'][$module_slug]['destinations'][$destination_slug]['titles'][$this->user_locale])) ? '' : $this->route_properties['modules'][$module_slug]['destinations'][$destination_slug]['titles'][$this->user_locale];
                    $nav_map[$module_slug]['destinations'][$destination_slug] = $title;
                }
            }
        }
        else
        {
            $filter = $this->get_user_modules_and_models_for_app($this->org_db_connection, $module_slug);
            $nav_map = [];
            foreach ($this->route_properties['modules'] as $module_slug => $m)
            {
                if (!array_key_exists($module_slug, $filter))
                    continue;

                $title = (!isset($this->route_properties['modules'][$module_slug]['titles'][$this->user_locale])) ? '' : $this->route_properties['modules'][$module_slug]['titles'][$this->user_locale];
                $nav_map[$module_slug] = [
                    'title' => $title,
                    'destinations' => []
                ];

                foreach ($filter[$module_slug] as $destination_slug)
                {
                    if (!in_array($destination_slug, $this->route_properties['modules'][$module_slug]['destinations']))
                        continue;

                    if($this->route_properties['modules'][$module_slug]['destinations'][$destination_slug]['show_in_nav'] === false)
                        continue;

                    $title = (!isset($this->route_properties['modules'][$module_slug]['destinations'][$destination_slug]['titles'][$this->user_locale])) ? '' : $this->route_properties['modules'][$module_slug]['destinations'][$destination_slug]['titles'][$this->user_locale];
                    $nav_map[$module_slug]['destinations'][$destination_slug] = $title;
                }
            }
        }

        if (empty($nav_map))
            return null;


        // build navigation

        $h = '';
        $app_root_url = JCT_URL_ROOT . $this->app_slug . '/';
        if ($this->route_properties['is_modular'])
        {
            $current_module = ($this->user_module_slug == self::DEFAULT_MODULAR_MODULE_SLUG) ? 'current-app-nav' : '';
            $h .= '<ul class="app-nav ' . $current_module . '" data-layer="' . self::DEFAULT_MODULAR_MODULE_SLUG . '">';

            // start with home nav
            // module could have multiple destinations, so show them first

            $home_module = $nav_map[self::DEFAULT_MODULAR_MODULE_SLUG];
            foreach ($home_module['destinations'] as $destination_slug => $title)
            {
                if($destination_slug === $this->destination_slug)
                    $h .= '<li class="current-view-nav">' . $title . '</li>';
                else
                    $h .= '<li><a href="' . $app_root_url . $destination_slug . '">' . $title . '</a></li>';
            }

            // then include links to other module navs

            foreach ($nav_map as $module_slug => $module)
            {
                if($module_slug === self::DEFAULT_MODULAR_MODULE_SLUG)
                    continue;

                $h .= '<li data-get="' . $module_slug . '"><span>' . $module['title'] . '</span></li>';
            }
            $h .= '</ul>';

            // then build navs for remaining modules

            foreach ($nav_map as $module_slug => $module)
            {
                if($module_slug === self::DEFAULT_MODULAR_MODULE_SLUG)
                    continue;

                $current_module = ($this->user_module_slug == $module_slug) ? 'current-app-nav' : '';
                $h .= '<ul class="app-nav ' . $current_module . '" data-layer="' . $module_slug . '">';
                $h .= '<li data-get="' . self::DEFAULT_MODULAR_MODULE_SLUG . '" class="app-nav-back-link"><span>' . Localisation::__('Back') . '</span></li>';

                foreach ($module['destinations'] as $destination_slug => $title)
                {
                    if($destination_slug === $this->destination_slug)
                        $h .= '<li class="current-view-nav">' . $title . '</li>';
                    else
                        $h .= '<li><a href="' . $app_root_url . $module_slug . '/' . $destination_slug . '">' . $title . '</a></li>';
                }

                $h .= '</ul>';
            }
        }
        else
        {
            // just need to show destinations for the only module...

            $current_module = ($this->user_module_slug == $module_slug) ? 'current-app-nav' : '';
            $h .= '<ul class="app-nav ' . $current_module . '" data-layer="' . $module_slug . '">';
            foreach ($nav_map as $module_slug => $module)
            {
                foreach ($module['destinations'] as $destination_slug => $title)
                {
                    if($destination_slug === $this->destination_slug)
                        $h .= '<li class="current-view-nav">' . $title . '</li>';
                    else
                        $h .= '<li><a href="' . $app_root_url . $destination_slug . '">' . $title . '</a></li>';
                }
            }
            $h .= '</ul>';
        }


        return $h;
    }

    private function get_user_modules_and_models_for_app(Database $db, $default_module_slug)
    {
        $db->query(" SELECT module, model FROM app_screen_user WHERE ( id = :id ) ");
        $db->bind(':id', $this->person_guid);
        $db->execute();
        $tmp = $db->fetchAllAssoc();

        $modules = [];
        foreach ($tmp as $t) {
            $module = (empty($t['module'])) ? $default_module_slug : $t['module'];
            if (!isset($modules[$module]))
                $modules[$module] = [];

            $modules[$module] = $t['model'];
        }

        return $modules;
    }



    // FOOTER functions

    private function build_footer()
    {
        $contact_link = JCT_URL_ROOT . 'contact';
        $login_link = JCT_URL_ROOT . 'login';
        $privacy_link = JCT_URL_ROOT . 'privacy';

        $have_app_nav = (!empty($this->app_nav));
        $app_nav_class = ($have_app_nav) ? 'with-app-nav' : 'without-app-nav';

        $h = <<<EOL
            <footer data-role="footer" class="screen-footer $app_nav_class">
             
                <div class="inner clearfix"> 
                
                    <div class="about footer-segment">  
                        <h6>About</h6>
                        <ul class="about-links"> 
                            <li><a href="$privacy_link">Privacy & GDPR</a></li>
                            <li><a href="$contact_link">Contact</a></li>
                            <li><a href="$login_link" class="login-link"><i class="fal fa-sign-in icon-left"></i><span>Site Login</span></a></li>
                        </ul> 
                    </div>
                    
                    <div class="company-info"> 
                        <h4>JCT Registration</h4>
                        <p class="address">Monaghan Education Centre, Knockaconny, Armagh Road, Co. Monaghan,  H18 E980</p>
                        <ul class="contact-links"> 
                            <li><span>T</span>+353 47 74 008</li>
                            <li><span>E</span><a href="mailto:info@jct.ie">info@jct.ie</a></li>
                        </ul> 
                    </div>
                    
                </div>
                
            </footer>
EOL;

        return $h;
    }

    private function build_view_scripts()
    {
        $core_url = JCT_URL_APPS . 'assets/js/';
        // required vendor scripts
        $h = '<script src="' . $core_url . 'jquery-3.2.1.min.js"></script>';
        //$h.= '<script src="' . JCT_URL_APPS . 'assets/js/jquery-ui-1.12.1.custom/external/jquery/jquery.js"></script>';
        $h.= '<script src="' . $core_url . 'jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>';
        $h.= '<script src="' . $core_url . 'jquery_touch_punch.js"></script>';

        // main platform script
        $h.= '<script src="' . $core_url . 'main.js"></script>';

        // view scripts
        if(!empty($this->view->view_scripts))
        {
            foreach($this->view->view_scripts as $key => $script)
            {
                if($key === 'codes')
                {
                    foreach($script as $i => $code)
                        $h.= '<script>' . $code . '</script>';

                    continue;
                }

                $prefix = strtolower(substr($script, 0, 4));

                // differentiate between external, core, and specific scripts
                switch($prefix)
                {
                    case('http'):
                        $h.= '<script src="' . $script . '"></script>';
                        break;
                    case('core'):
                        $split = explode('/', $script);
                        array_shift($split);
                        $file_path = implode(JCT_DE, $split) . '.js';
                        $file_url = implode('/', $split) . '.js';
                        $path = JCT_PATH_APPS . 'assets' . JCT_DE . 'js' . JCT_DE . $file_path;
                        if(is_readable($path))
                            $h.= '<script src="' . JCT_URL_APPS . 'assets/js/' . $file_url . '"></script>';
                        break;
                    default:
                        $path = JCT_PATH_APPS . $this->view->app_param . JCT_DE . 'assets' . JCT_DE . 'js' . JCT_DE . $script . '.js';
                        if(is_readable($path))
                            $h.= '<script src="' . JCT_URL_APPS . $this->view->app_param  . '/assets/js/' . $script . '.js"></script>';
                        break;
                }
            }
        }

        return $h;
    }
}