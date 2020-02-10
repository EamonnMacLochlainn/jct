var values = {};
values.app_param = 'user';
values.model_param = 'Home';
values.csrf = $('meta[name="csrf"]').attr("content");


var page_content = $('.screen-content > .light-panel'),

    credentials_panel = page_content.find('> .credentials'),
    email_input = credentials_panel.find('> .email > div > input[type="email"]'),
    update_email_btn = credentials_panel.find('> .email > div > button.update-email'),
    password_wrap = credentials_panel.find('> .password-wrap'),
    new_password_input = password_wrap.find('> .new-password > input'),
    confirm_password_input = password_wrap.find('> .confirm-password > div > input[type="password"]'),
    update_password_btn = password_wrap.find('> .confirm-password > div > button.update-password'),

    name_panel = page_content.find('> .name'),
    update_name_btn = name_panel.find('> button.update-name'),

    contact_panel = page_content.find('> .contact'),
    phones_ctn = contact_panel.find('> .phones'),
    mobile_input = phones_ctn.find('> .mobile > input'),
    landline_input = phones_ctn.find('> .landline > input'),
    country_sel = contact_panel.find('> .country > select'),
    address_lines_ctn = contact_panel.find('> .address-lines'),
    address_params_ctn = contact_panel.find('> .address-params'),
    county_sel = address_params_ctn.find('> .county > select'),
    show_county_input = address_params_ctn.find('> .show-county > input'),
    your_address_ctn = contact_panel.find('> .your-address > .address-ctn'),
    update_address_btn = contact_panel.find('> button.update-address');


function get_user()
{
    var args = $.extend(true, {}, values);
    args.method_param = 'get_user';

    __ajax_submit(args, get_user_complete)
}

function get_user_complete(r)
{
    var opts = $.extend(true, {}, _dialog_options);
    if(r.hasOwnProperty('error'))
    {
        opts.message = r.error;
        __display_dialog(opts);
        return false;
    }

    populate_user(r);
}

function populate_user(user)
{
    var country_id = parseInt(user.country_id);
    if(country_id !== 0)
    {
        country_sel.val(country_id).trigger('change');
        setTimeout(function()
        {
            $.each(user, function(key,val)
            {
                var input = page_content.find(':input[name="' + key + '"]:not(.button)');

                if(input.length === 0)
                    return true;

                input.val(val);
            });

            update_address_str();
        },200);
    }
    else
    {
        $.each(user, function(key,val)
        {
            var input = page_content.find(':input[name="' + key + '"]:not(.button)');

            if(input.length === 0)
                return true;

            input.val(val);
        });

        update_address_str();
    }
}






update_email_btn.click(function(e)
{
    e.preventDefault();

    var email = email_input.val(),
        tmp = __strip_all_whitespace(email);

    if(tmp === '')
    {
        __show_feedback_toasting('negative', 'You must enter an email address!');
        return false;
    }

    var args = $.extend(true, {}, values);
    args.method_param = 'update_email';
    args.email = email;

    __ajax_submit(args, update_email_complete);

    return false;
});

function update_email_complete(r)
{
    if(r.hasOwnProperty('error'))
    {
        __show_feedback_toasting('negative', r.error);
        return false;
    }

    __show_feedback_toasting('positive', 'Email Updated!');
}



update_password_btn.click(function(e)
{
    e.preventDefault();

    var new_password = new_password_input.val(),
        confirm_password = confirm_password_input.val();

    var status = validate_password(new_password);
    if(!status.ok)
    {
        __show_feedback_toasting('negative',status.reason);
        return false;
    }

    if(new_password !== confirm_password)
    {
        __show_feedback_toasting('negative', 'Your passwords do not match!');
        return false;
    }

    var args = $.extend(true, {}, values);
    args.method_param = 'update_password';
    args.password = new_password;

    __ajax_submit(args, update_password_complete);

    return false;
});

function validate_password(password)
{
    var status = {},
        required_len = 8;

    status.ok = false;
    status.reason = '';

    // has whitespace
    if(RegExp('[\\s]').test(password))
    {
        status.reason = 'Your password cannot contain any spaces.';
        return status;
    }

    // too short
    if(password.length < required_len)
    {
        status.reason = 'Your password must be at least ' + required_len + ' characters long.';
        return status;
    }

    // dis-allowed chars
    if(!password.match(/^[A-Za-z0-9!@#$%^&*_]*$/))
    {
        status.reason = 'Your password may only contain alphanumeric characters or !@#$%^&*_';
        return status;
    }

    status.ok = true;
    return status;
}

function update_password_complete(r)
{
    if(r.hasOwnProperty('error'))
    {
        __show_feedback_toasting('negative', r.error);
        return false;
    }

    __show_feedback_toasting('positive', 'Password Updated!');
}


update_name_btn.click(function(e)
{
    e.preventDefault();

    var args = $.extend(true, {}, values);
    args.method_param = 'update_name';

    var inputs = name_panel.find(':input:not(.button)');
    $.each(inputs, function()
    {
        var el = $(this);
        args[el.attr('name')] = el.val();
    });

    __ajax_submit(args, update_name_complete);

    return false;
});

function update_name_complete(r)
{
    if(r.hasOwnProperty('error'))
    {
        __show_feedback_toasting('negative', r.error);
        return false;
    }

    __show_feedback_toasting('positive', 'Name Updated!');
}



country_sel.on('change', function()
{
    var country_id = parseInt(country_sel.val());
    if(country_id === 0)
    {
        county_sel.val(0);
        show_county_input.prop('checked', true);

        $.each(contact_panel.find(':input').not(this), function(){ $(this).prop('disabled', true); });
        $.each(contact_panel.find('input[type="text"]'), function(){ $(this).val(''); });
        $.each(contact_panel.find('input[type="number"]'), function(){ $(this).val(0); });

        your_address_ctn.text('');
        return false;
    }

    var args = $.extend(true, {}, values);
    args.method_param = 'get_counties';
    args.country_id = country_id;

    __ajax_submit(args, get_counties_complete)
});

function get_counties_complete(r)
{
    if(r.hasOwnProperty('error'))
    {
        __show_feedback_toasting('negative', r.error);
        return false;
    }

    county_sel.empty().append('<option value="0">--</option>');
    $.each(r, function(i, c)
    {
        county_sel.append('<option value="' + c.id + '">' + c.title + '</option>');
    });

    $.each(contact_panel.find(':input'), function(){ $(this).prop('disabled', false); });

    update_address_str();
}

function update_address_str()
{
    var arr = [],
        country_id = parseInt(country_sel.val()),
        county_id = parseInt(county_sel.val()),
        show_county = (show_county_input.is(':checked')),

        city_town = address_params_ctn.find('>.city_town > input').val(),
        postcode = parseInt(address_params_ctn.find('>.postcode > input').val()),
        county = county_sel.find('>:selected').text(),
        country = country_sel.find('>:selected').text(),
        eircode = address_params_ctn.find('>.eircode > input').val();

    $.each(address_lines_ctn.find('input'), function()
    {
        var line = $(this).val(),
            tmp = __strip_all_whitespace(line);

        if(tmp === '')
            return true;

        arr.push(line);
    });

    var tmp = __strip_all_whitespace(city_town);
    if(tmp !== '')
    {
        if(postcode > 0)
            city_town+= ' ' + String(postcode);
        arr.push(city_town);
    }

    if(show_county && (county_id > 0))
        arr.push(county);

    if(country_id !== 372)
        arr.push(country);

    tmp = __strip_all_whitespace(eircode);
    if(tmp !== '')
        arr.push(eircode);

    your_address_ctn.text(arr.join(', '));
}

$('.add1 input, .add2 input, .add3 input, .add4 input, .city_town input, .eircode input').on('keyup', function()
{
    update_address_str();
});

$('.postcode').on('change', function()
{
    update_address_str();
});

county_sel.on('change', function()
{
    update_address_str()
});

show_county_input.on('change', function()
{
    update_address_str()
});




update_address_btn.click(function(e)
{
    e.preventDefault();

    var country_id = parseInt(country_sel.val());
    if(country_id === 0)
    {
        __show_feedback_toasting('negative', 'You must select a Country.');
        return false;
    }


    var args = $.extend(true, {}, values);
    args.method_param = 'update_contact';
    args.country_id = country_id;
    args.mobile = mobile_input.val();
    args.landline = landline_input.val();
    args.street_address = [];

    $.each(address_lines_ctn.find('input'), function()
    {
        var val = $(this).val(),
            tmp = __strip_all_whitespace(val);

        if(tmp === '')
            return true;

        args.street_address.push(val);
    });

    args.city_town = address_params_ctn.find('>.city_town > input').val();
    args.postcode = address_params_ctn.find('>.postcode > input').val();
    args.county_id = county_sel.val();
    args.show_county = (show_county_input.is(':checked')) ? 1 : 0;
    args.eircode = address_params_ctn.find('>.eircode > input').val();


    __ajax_submit(args, update_contact_complete);

    return false;
});

function update_contact_complete(r)
{
    if(r.hasOwnProperty('error'))
    {
        __show_feedback_toasting('negative', r.error);
        return false;
    }

    __show_feedback_toasting('positive', 'Contact Details Updated!');
}


get_user();