
let values = {};
values.app_param = 'site';
values.model_param = 'Home';
values.csrf = $('meta[name="csrf"]').attr("content");

let password_input = $('input[name="password"]'),
    username_input = $('input[name="username"]');

$('input[name="show_password"]').on('change', function()
{
    let checked = ($(this).is(':checked'));

    if(checked)
        password_input.attr('type','text');
    else
        password_input.attr('type','password');
});

$('#login-btn').on('click', function(e)
{
    e.preventDefault();

    let args = $.extend(true, {}, values);
    args.method_param = 'login_user';
    args.username = username_input.val();
    args.password = password_input.val();

    if(__strip_all_whitespace(args.password) === '' || __strip_all_whitespace(args.username) === '')
    {
        let opts = $.extend(true, {}, _dialog_options);
        opts.message = 'Both Username and Password fields must be filled to log in.';
        __display_dialog(opts);
        return false;
    }

    console.log('submitted');
    __ajax_submit(args, null, login_user_complete);

    return false;
});

function login_user_complete(msg)
{
    let r = __ajax_default_error_check(msg);

    if(r.hasOwnProperty('success'))
        window.location.href = __core.root_url + 'Dashboard/';
}


$('#reset-password').on('click', function(e)
{
    e.preventDefault();

    let ctn = $('<label class="username" />');
    ctn.append('<span class="label-text">Please provide your username below:</span>')
        .append('<input type="text" name="username" value="" />');

    let opts = $.extend(true, {}, _dialog_options);
    opts.is_error = false;
    opts.title = 'Reset Password';
    opts.message = '<p>Reset your password and<br/>have a copy emailed to you:</p>';
    opts.message+= ctn.prop('outerHTML');
    opts.buttons = [
        {
            text: 'Cancel',
            'class': 'negative',
            click: function()
            {
                $(this).dialog('close').dialog('destroy').remove();
            }
        },
        {
            text: 'Reset Password',
            'class': 'regular',
            click: function()
            {
                let args = $.extend(true, {}, values);
                args.method_param = 'reset_password';
                args.username = $(this).find('input[name="username"]').val();

                __ajax_submit(args, null, reset_password_complete);
                $(this).dialog('close').dialog('destroy').remove();
            }
        }
    ];

    __display_dialog(opts);
    return false;
});

function reset_password_complete(msg)
{
    let r = __ajax_default_error_check(msg);

    console.log(r);

    if(!r.hasOwnProperty('success'))
        return false;

    let opts = $.extend(true, {}, _dialog_options);
    opts.is_error = false;
    opts.message = '<p>Your password has been re-sent to the email address associated with your account.</p>';
    __display_dialog(opts);
    return false;
}





