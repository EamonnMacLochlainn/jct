let values = {};
values.app_param = 'site';
values.model_param = 'Home';
values.csrf = $('meta[name="csrf"]').attr("content");

let form = $('form.login'),
    username_input = form.find('> fieldset > .username > input'),
    password_input = form.find('> fieldset > .password > input'),
    org_sel = form.find('> fieldset > .org > select'),
    role_sel = form.find('> fieldset > .role > select'),
    login_btn = form.find('> .buttonset button.login');


login_btn.click(function(e)
{
    e.preventDefault();

    let username = username_input.val(),
        password = password_input.val();

    if(username.length === 0)
    {
        __show_feedback_toasting('negative', 'You have not supplied a Username!');
        return false;
    }

    if(password.length === 0)
    {
        __show_feedback_toasting('negative', 'You have not supplied a Password!');
        return false;
    }

    let args = $.extend(true, {}, values);
    args.username = username;
    args.password = password;
    args.org_id = org_sel.val();
    args.role_id = role_sel.val();
    args.method_param = 'login_user';

    __ajax_submit(args, login_result);
    return false;
});

function login_result(r)
{
    console.log(r);
    if(r.hasOwnProperty('error'))
    {
        let opts = $.extend(true, {}, _dialog_options);
        opts.message = r.error;
        __display_dialog(opts);
        return false;
    }

    if(r.hasOwnProperty('org_choice'))
    {
        org_sel.empty().append('<option value="0">--</option>');
        $.each(r.org_choice, function(i,o){ org_sel.append('<option value="' + o.id + '">' + o.title + '</option>'); });

        org_sel.parent().slideDown();
        return false;
    }
    //window.location.href = __core.root_url + '/dashboard';
}

