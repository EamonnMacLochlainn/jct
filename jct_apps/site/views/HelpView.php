<?php
/**
 * Created by PhpStorm.
 * User: Eamonn
 * Date: 28/09/2017
 * Time: 10:30
 */

namespace JCT\site;


use JCT\BaseView;
use JCT\ViewInterface;

class HelpView extends BaseView implements ViewInterface
{
    function __construct(HelpModel $model, $permission_type)
    {
        parent::__construct();
        $this->model = $model;
        $this->permission_type = $permission_type;

        $this->update_app_param();

        $this->meta_robots_follow = true;
    }

    function update_app_param()
    {
        $this->app_param = 'site';
    }

    function index()
    {
        $this->screen_title = 'Help';
        $this->view_stylesheets[] = 'help';
        $this->view_scripts[] = 'help';

        $h = '<div class="inner-wrap">';
        $h.= <<<EOS
        
        <div class="light-panel clearfix">
        
            <div class="preamble">
                <p style="text-align: justify">This website is intended to facilitate the registration of teachers to receive CPD training. Registration should be carried out by a single designated individual per school.</p>
                <p style="text-align: justify">All teachers within a school should be entered into the database (whether or not they intend to receive CPD at this time) by that designated registrar, along with their taught subjects, to enable the proper planning and coordination of training events.</p>
                <p style="text-align: justify">Only one login is provided per school and should be kept by the school's designated registrar. Each school's credentials are communicated to the school directly from the JCT offices by letter.</p>
                
                <br/>
                <h4>Registration</h4>
                <p style="text-align: justify">As different event types may have differing requirements for registration, any instructions that may be required will be detailed alongside that event's registration form.</p>
                
                <br/>
                <h4>Privacy</h4>
                <p style="text-align: justify">Registration and attendance information for any individual teacher will be visible to their school's registrar and to relevant JCT officials. Personal details are not collected. For further details on privacy issues, please see our <a href="privacy">Privacy & GDPR</a> page.</p>

            </div>
            
            <div class="contact">
                <br/>
                <p>Still need help?</p>
                <a class="regular" href="contact-us">Contact Us</a>
            </div>
            
        </div>
EOS;

        $h.= '</div>';

        $this->screen_content = $h;


    }
}