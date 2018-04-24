<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 04/05/2016
 * Time: 13:48
 */

namespace JCT;


class Render
{
    private $browser;

    private $global_nav;
    private $section_nav;
    private $view;


    private $html;

    function __construct(\Browser $browser)
    {
        $this->browser = $browser;
    }

    function set_global_navigation($global_nav_html)
    {
        $this->global_nav = $global_nav_html;
    }

    function set_section_navigation($section_nav_html)
    {
        $this->section_nav = $section_nav_html;
    }





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

        $h.= '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat|Open+Sans:400,800&subset=latin-ext">';

        $h.= '</head>';

        return $h;
    }

    private function build_meta()
    {
        // does not include TileColor/Image meta tags
        // which are included with favicon links

        $csrf = (!empty($_SESSION['jct']['csrf'])) ? $_SESSION['jct']['csrf'] : '';
        $locale = (!empty($_SESSION['jct']['locale'])) ? $_SESSION['jct']['locale'] : 'en_GB';

        $h = '<meta charset="utf-8">';
        $h.= '<meta http-equiv="x-ua-compatible" content="ie=edge">';
        $h.= '<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=1">';
        $h.= '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">';
        $h.= '<meta name="csrf" content="' . $csrf . '">';
        $h.= '<meta name="locale" content="' . $locale . '">';
        $h.= '<meta name="theme-color" content="#27873D">';
        $h.= '<meta name="_inst_dir" content="' . JCT_INSTALLATION_DIR . '">';

        if(!empty($this->meta_description))
            $h.= '<meta name="description" content="' . $this->meta_description . '">';

        if(!$this->view->meta_robots_follow)
            $h.= '<meta name="robots" content="noindex">';

        return $h;
    }

    private function build_view_stylesheets()
    {
        // reset
        $h = '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . 'assets/css/normalize.css" />';

        // required vendor styles
        $h.= '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . 'assets/fonts/fontawesome/fontawesome-all.min.css" />';
        $h.= '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . 'assets/js/jquery-ui-1.12.1.custom/jquery-ui.min.css" />';

        // main platform styles
        $h.= '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . 'assets/css/main.css" />';

        // section styles
        if(is_readable(JCT_PATH_SECTIONS . $this->view->section_slug . JCT_DE . 'assets' . JCT_DE . 'css' . JCT_DE . 'style.css'))
            $h.= '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . $this->view->section_slug . '/assets/css/style.css" />';

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
                        $path = JCT_PATH_SECTIONS . 'assets' . JCT_DE . 'css' . JCT_DE . $filename . '.css';
                        if(is_readable($path))
                            $h.= '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . 'assets/css/' . $filename . '.css" />';
                        break;
                    default:
                        $path = JCT_PATH_SECTIONS . $this->view->section_slug . JCT_DE . 'assets' . JCT_DE . 'css' . JCT_DE . $stylesheet . '.css';
                        if(is_readable($path))
                            $h.= '<link rel="stylesheet" href="' . JCT_URL_SECTIONS . $this->view->section_slug  . '/assets/css/' . $stylesheet . '.css" />';
                        break;
                }
            }
        }

        return $h;
    }

    private function build_view_classes()
    {
        // ensure basic class
        if(empty($this->view->screen_classes))
            $this->view->screen_classes[] = 'default';

        // add browser class
        $browser = $this->browser->getBrowser();
        array_unshift($this->view->screen_classes, Helper::slugify($browser));

        return implode(' ', $this->view->screen_classes);
    }

    private function build_tab_title()
    {
        $tab_titles = [];
        $tab_titles[] = 'JCT Registration';

        // get section_param
        // note that section_slug may include module slug, so split that off
        $split = explode(JCT_DE, $this->view->section_slug);
        $section_slug = array_shift($split);
        $ar = new SectionRegistry();
        $section = $ar->get_section_registry($section_slug);
        if($section->title !== null)
            $tab_titles[] = $section->title;

        $view_tab_title = (!empty($this->view->screen_tab_title)) ? $this->view->screen_tab_title : null;
        if( ($view_tab_title !== null) && ($view_tab_title !== $section->title) )
            $tab_titles[] = $view_tab_title;

        $tab_titles = array_reverse($tab_titles);

        return implode(' | ', $tab_titles);
    }






    private function build_footer()
    {
        $contact_link = JCT_URL_ROOT . 'contact-us';
        $privacy_link = JCT_URL_ROOT . 'privacy';
        $anydesk_link = JCT_URL_MEDIA . 'download?p=databiz_rs_anydesk.exe';
        $teamviewer_link = JCT_URL_MEDIA . 'download?p=databiz_rs_teamviewer_11.exe';
        $teamviewer_ios_link = JCT_URL_MEDIA . 'download?p=databiz_rs_teamviewer_ios.exe';

        $h = <<<EOL
            <footer data-role="footer" class="screen-footer"> 
                <div class="inner clearfix"> 
                    <ul class="footer-cols"> 
                       <li> 
                           <h6>About</h6>
                           <ul class="about-links"> 
                               <li><a href="$privacy_link">Privacy & GDPR</a></li>
                               <li><a href="$contact_link">Contact</a></li>
                           </ul> 
                       </li> 
                       <li> 
                           <h6>Remote Support</h6>
                           <ul class="support-links"> 
                               <li><a href="$anydesk_link">AnyDesk</a></li>
                               <li><a href="$teamviewer_link">Teamviewer (Win)</a></li>
                               <li><a href="$teamviewer_ios_link">Teamviewer (iOS)</a></li>
                           </ul> 
                       </li> 
                    </ul>
                </div>
            </footer>
EOL;

        return $h;
    }

    private function build_view_scripts()
    {
        $core_url = JCT_URL_SECTIONS . 'assets/js/';

        // required vendor scripts
        $h = '<script src="' . $core_url . 'jquery-3.3.1.min.js"></script>';
        $h.= '<script src="' . $core_url . 'jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>';
        $h.= '<script src="' . $core_url . 'jquery_touch_punch.js"></script>';

        // main platform script
        $h.= '<script src="' . $core_url . 'main.js"></script>';

        // view scripts
        if(!empty($this->view->view_scripts))
        {
            foreach($this->view->view_scripts as $script)
            {
                $prefix = strtolower(substr($script, 0, 4));

                // differentiate between external, core, and specific scripts
                switch($prefix)
                {
                    case('http'):
                        $h.= '<script src="' . $script . '"></script>';
                        break;
                    case('core'):
                        $split = explode('/', $script);
                        $filename = array_pop($split);
                        $path = JCT_PATH_SECTIONS . 'assets' . JCT_DE . 'js' . JCT_DE . $filename . '.js';
                        if(is_readable($path))
                            $h.= '<script src="' . JCT_URL_SECTIONS . 'assets/js/' . $script . '.js"></script>';
                        break;
                    default:
                        $path = JCT_PATH_SECTIONS . $this->view->section_slug . JCT_DE . 'assets' . JCT_DE . 'js' . JCT_DE . $script . '.js';
                        if(is_readable($path))
                            $h.= '<script src="' . JCT_URL_SECTIONS . $this->view->section_slug  . '/assets/js/' . $script . '.js"></script>';
                        break;
                }
            }
        }

        return $h;
    }

    function build_view($view)
    {
        $this->view = $view;

        $have_section_nav = (!empty($this->section_nav));
        $section_nav_class = ($have_section_nav) ? 'with-section-nav' : 'without-section-nav';


        // open doc
        $h = '<!DOCTYPE html><html lang="en">';
        $h.= $this->build_header();

        // open body, screen wrap
        $h.= '<body class="' . $this->build_view_classes() . '">';
        $h.= '<div class="screen-wrap ' . $section_nav_class . ' clearfix">';
        $h.= '<a name="top"></a>';

        // section navigation
        if($have_section_nav)
            $h.= $this->section_nav;

        // view header
        $h.= '<header data-role="header" class="screen-header ' . $section_nav_class . '">';
        $h.= $this->global_nav;
        $h.= '</header>';

        // content
        $h.= '<section data-role="page" class="screen-content ' . $section_nav_class . ' clearfix">';
        if(!empty($this->view->screen_title))
            $h.= '<h1 class="screen-title">' . $this->view->screen_title . '</h1>';
        if(!empty($this->view->screen_blurb))
            $h.= '<p class="screen-blurb">' . $this->view->screen_blurb . '</p>';
        $h.= $this->view->screen_content . '</section>';

        // close screen wrap
        $h.= '</div>';
        $h.= '<a href="#top" class="back-to-top fa-stack"><i class="fa fa-circle fa-stack-2x"></i><i class="fa fa-chevron-up fa-stack-1x fa-inverse"></i></a>';

        // view footer
        $h.= $this->build_footer();

        // include JS scripts
        $h.= $this->build_view_scripts();

        //$h.= Helper::show($_SESSION, true);

        // close body, doc
        $h.= '</body>';
        $h.= '</html>';

        return $h;
    }
}