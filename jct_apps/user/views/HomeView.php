<?php


namespace JCT\user;


use JCT\BaseView;
use JCT\Localisation;
use JCT\ViewInterface;

class HomeView extends BaseView implements ViewInterface
{
    function __construct(HomeModel $model, $permission_type)
    {
        parent::__construct();
        $this->model = $model;
        $this->permission_type = $permission_type;

        $this->update_app_param();

        $this->meta_robots_follow = true;
    }

    function update_app_param()
    {
        $this->app_param = 'user';
    }

    function index()
    {
        $this->screen_title = 'User';
        $this->screen_tab_title = 'User';
        $this->view_scripts[] = 'home';
        $this->view_stylesheets[] = 'home';

        $salt_options = '';
        foreach($this->model->data['salutations'] as $id => $title)
            $salt_options.= '<option value="' . $id . '">' . $title . '</option>';

        $country_options = '';
        foreach($this->model->data['country'] as $id => $prm)
            $country_options.= '<option value="' . $id . '">' . $prm['title'] . '</option>';


        $h = <<<EOS
<div class="light-panel">  

    <div class="panel credentials">  
        <h4>User Credentials</h4>
        
        <label class="email">  
            <span class="label-text">Email Address:</span>
            <div>  
                <input type="email" name="email" value="" />
                <button class="button regular update-email">Update</button>  
            </div>
        </label>
        
        <div class="password-wrap">  
        
            <p>Your password needs to be at least 8 characters long, and may only contain alpha-numeric or !@#$%^&*_ characters.</p>
        
            <label class="password new-password">  
                <span class="label-text">New Password:</span>
                <input type="password" name="new_password" value="" /> 
            </label>
        
            <label class="password confirm-password">  
                <span class="label-text">Confirm Password:</span>
                <div> 
                    <input type="password" name="confirm_password" value="" /> 
                    <button class="button regular update-password">Update</button>
                </div>
            </label>
            
        </div>
        
    </div>
  
    <div class="panel name">   
    <h4>Name</h4>
    
        <label class="salt">  
            <span class="label-text">Salutation:</span>
            <select name="salt_id"><option value="0">--</option>$salt_options
            </select>
        </label>
    
        <label class="fname">  
            <span class="label-text">First Name:</span>
            <input type="text" name="fname" value="" />
        </label>
        
        <label class="lname">  
            <span class="label-text">Last Name:</span>
            <input type="text" name="lname" value="" />
        </label>
        
        <label class="salute-name">  
            <span class="label-text">Known As:</span>
            <input type="text" name="salute_name" value="" />
        </label>
        
        <button class="button regular update-name">Update</button>
    
    </div>
    
    <div class="panel contact">  
        <h4>Contact</h4>
        
        <label class="country">
            <span class="label-text">Country:</span>
            <select name="country_id"><option value="0">--</option>$country_options
            </select>
        </label>
        
        <div class="phones">  
        
            <label class="mobile">  
                <span class="label-text">Mobile:</span>
                <input type="text" name="mobile" value="" disabled />
            </label>
            
            <label class="landline">  
                <span class="label-text">Landline:</span>
                <input type="text" name="landline" value="" disabled />
            </label>
        
        </div>
        
        <div class="address-lines"> 
            <h5>Street Address (without city/town or county):</h5>
            <label class="add1">  
                <input type="text" name="add1" placeholder="Line 1" value="" disabled />
            </label>
            <label class="add2">  
                <input type="text" name="add2" placeholder="Line 2" value="" disabled />
            </label>
            <label class="add3">  
                <input type="text" name="add3" placeholder="Line 3" value="" disabled />
            </label>
            <label class="add4">  
                <input type="text" name="add4" placeholder="Line 4" value="" disabled />
            </label>
        </div>
        
        <div class="address-params">  
        
            <label class="city_town clearfix">
                <span class="label-text">City / Town:</span>  
                <input type="text" name="city_town" value="" disabled />
            </label>
            
            <label class="postcode clearfix">
                <span class="label-text">Dublin Postcode (zero for N/A):</span>  
                <input type="number" name="postcode" value="0" min="0" max="24" disabled />
            </label>
           
            <label class="county clearfix">
                <span class="label-text">County:</span>
                <select name="county_id" disabled ><option value="0" >--</option>
                </select>
            </label>
            
            <label class="show-county clearfix">  
                <span class="label-text">Include County in Postal Address?</span>
                <input type="checkbox" name="show_county" value="1" checked disabled />
            </label>
            
            <label class="eircode clearfix">
                <span class="label-text">Eircode:</span>  
                <input type="text" name="eircode" value="" disabled />
            </label>
            
        </div>
        
        <div class="your-address">  
            <p>Your address will read as:</p>
            <span class="address-ctn"></span>
        </div>
        
        <button class="button regular update-address">Update</button>
    
    </div>

</div>
EOS;

        $this->screen_content = $h;
    }
}