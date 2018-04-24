/**
 * Created by Eamonn on 28/06/2017.
 */

var values = {};
values.app_param = 'site';
values.model_param = 'Login';
values.csrf = $('meta[name="csrf"]').attr("content");

var form = $('form.login');

$(document).ready(function()
{
    setTimeout(function()
    {
        form.fadeIn();
    }, 250);
});

$('.login-help-trigger').click(function(e)
{
    e.preventDefault();

    window.location.href = __core.root_url + '/Help';
    return false;
});

$('.password-request').click(function(e)
{
    e.preventDefault();

    var args = $.extend(true, {}, values);
    args.method_param = 'get_countries';

    __ajax_submit(args, null, get_countries_complete);
});

function get_countries_complete(msg)
{
    if( (msg === '') || (msg === 'null') )
        return false;

    var r = JSON.parse(msg.responseText),
        opts = $.extend(true, {}, _dialog_options);

    if(r.hasOwnProperty('error'))
    {
        opts.is_error = true;
        opts.message = r.error;

        __display_dialog(opts);
        return false;
    }
    else
    {
        opts.is_error = false;
        opts.title = 'Request a Password';
        opts.height = 300;
        opts.buttons = [
            {
                text: 'Send Request',
                'class': 'positive send-password-request',
                click: function()
                {
                    var request_form = $(this);

                    var country_code = request_form.find('select[name="country_code"]').val(),
                        contact_num = request_form.find('input[name="mobile"]').val(),
                        email = request_form.find('input[name="email"]').val();

                    if( (contact_num === '') || (email === '') )
                        return false;

                    request_password(country_code, contact_num, email);

                    $(this).dialog('close').dialog('destroy').remove();
                }
            },
            {
                text: 'Cancel',
                'class': 'negative',
                click: function()
                {
                    $(this).dialog('close').dialog('destroy').remove();
                }
            }
        ];

        var request_ctn = $('<div class="request-form" />');
        request_ctn.append('<p>Please enter your contact number and email address (both are required)</p>');

        var countries_ctn = $('<label class="country-code"/>'),
            countries_select = $('<select name="country_code"/>'),
            contact_num_ctn = $('<label class="contact-num left-iconed-input">'),
            email_ctn = $('<label class="email left-iconed-input">');

        $.each(r, function(i, c) { countries_select.append('<option value="' + c.attribute + '">' + c.title + '</option>'); });
        countries_ctn.append(countries_select);

        contact_num_ctn
            .append('<i class="fa fa-phone"></i>')
            .append('<input type="text" name="mobile" tabindex="-1" autofocus autocomplete="off" placeholder="Contact No." data-role="contact_num" value=""/>');
        email_ctn
            .append('<i class="fa fa-envelope"></i>')
            .append('<input type="text" name="email" tabindex="0" autocomplete="on" placeholder="Email" data-role="email" value=""/>');

        request_ctn
            .append(countries_ctn)
            .append(contact_num_ctn)
            .append(email_ctn);

        opts.message = request_ctn;
        __display_dialog(opts);
        return false;
    }
}

function request_password(country_code, mobile, email)
{
    var args = $.extend(true, {}, values);
    args.method_param = 'request_password';
    args.country_code = country_code;
    args.mobile = mobile;
    args.email = email;

    __ajax_submit(args, null, request_password_complete);
}

function request_password_complete(msg)
{
    if( (msg === '') || (msg === 'null') )
        return false;

    var r = JSON.parse(msg.responseText),
        opts = $.extend(true, {}, _dialog_options);

    if(r.hasOwnProperty('error'))
    {
        opts.is_error = true;
        opts.message = r.error;

        __display_dialog(opts);
        return false;
    }
    else
    {
        opts.is_error = false;
        opts.title = 'Password generated';
        opts.message = '<p>A new password has been generated, and emailed to you.</p>' +
            //'<p>Use that password to log in - then you can change it to something more memorable.</p>' +
            '<p style="font-size: 0.8em">(You may have to wait a minute or two to give the email a chance ' +
            'to arrive. If in doubt, check your junk mail folders, or just request another password)</p>';

        __display_dialog(opts);
        return false;
    }
}





$('button.login').click(function(e)
{
    e.preventDefault();

    var email = $('input[name="email"]').val(),
        password = $('input[name="password"]').val();

    login_user(email, password, '', '');
    return false;
});

function login_user(email, password, org_id, role_id)
{
    email = (typeof email === 'undefined') ? '' : email;
    password = (typeof password === 'undefined') ? '' : password;
    org_id = (typeof org_id === 'undefined') ? 0 : org_id;
    role_id = (typeof role_id === 'undefined') ? 0 : role_id;

    var args = $.extend(true, {}, values);
    args.method_param = 'login_user';
    args.email = email;
    args.password = password;
    args.org_id = org_id;
    args.role_id = role_id;

    var repopulate_args = {}; // combat browser saved logins
    repopulate_args.email = email;
    repopulate_args.password = password;

    __ajax_submit(args, null, login_user_complete, null, repopulate_args);
}

function login_user_complete(msg, repopulate_args)
{
    if( (msg === '') || (msg === 'null') )
        return false;

    var r = JSON.parse(msg.responseText),
        opts = $.extend(true, {}, _dialog_options);

    if(r.hasOwnProperty('error'))
    {
        opts.is_error = true;
        opts.message = r.error;

        __display_dialog(opts);
        return false;
    }
    else
    {
        $('input[name="email"]').val(repopulate_args.email);
        $('input[name="password"]').val(repopulate_args.password);


        if(r.hasOwnProperty('orgs'))
        {
            opts = $.extend(true, {}, _dialog_options);
            opts.is_error = false;
            opts.title = 'Pick Organisation';
            opts.message = '<p>You are registered to more than one Organisation.</p>' +
                '<p>Please pick from the options below:</p>' +
                '<select name="org_guid"><option value="0">--</option>';
            $.each(r.orgs, function(i, org)
            {
                opts.message+= '<option value="' + org.id + '">' + org.org_name + ' (' + org.guid + ')' + '</option>';
            });
            opts.message+='</select>';
            opts.buttons = [
                {
                    text: 'Select',
                    'class': 'regular',
                    click: function()
                    {
                        var sel = $(this).find('select[name="org_guid"]'),
                            org_id = parseInt(sel.val());

                        var email = $('input[name="email"]').val(),
                            password = $('input[name="password"]').val();

                        if(org_id === 0)
                            return false;

                        $(this).dialog('close').dialog('destroy').remove();

                        login_user(email,password,org_id);
                        return false;
                    }
                }
            ];
            __display_dialog(opts);
            return false;
        }

        if(r.hasOwnProperty('roles'))
        {
            if(!r.hasOwnProperty('org_id'))
            {
                opts = $.extend(true, {}, _dialog_options);
                opts.is_error = 'true';
                opts.message = '<p>Could not log you in: Organisation ID not returned with Role options.</p>';

                __display_dialog(opts);
                return false;
            }

            var org_id = parseInt(r.org_id);

            opts = $.extend(true, {}, _dialog_options);
            opts.is_error = false;
            opts.title = 'Pick Organisation';
            opts.message = '<p>You are registered to more than one role for this Organisation.</p>' +
                '<p>Please pick from the options below:</p>' +
                '<select name="role_id"><option value="0">--</option>';
            $.each(r.roles, function(i, role)
            {
                opts.message+= '<option value="' + role.id + '">' + role.title + '</option>';
            });
            opts.message+='</select>';
            opts.buttons = [
                {
                    text: 'Select',
                    'class': 'regular',
                    click: function()
                    {
                        var sel = $(this).find('select[name="role_id"]'),
                            role_id = parseInt(sel.val());

                        var email = $('input[name="email"]').val(),
                            password = $('input[name="password"]').val();

                        if(role_id === 0)
                            return false;

                        $(this).dialog('close').dialog('destroy').remove();
                        login_user(email,password,org_id,role_id);
                        return false;
                    }
                }
            ];
            __display_dialog(opts);
            return false;
        }

        if(r.hasOwnProperty('complete'))
            window.location.href = __core.root_url + '/Dashboard/';
    }
}