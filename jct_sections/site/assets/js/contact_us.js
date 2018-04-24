/**
 * Created by Eamonn on 28/06/2017.
 */

var values = {};
values.app_param = 'site';
values.model_param = 'ContactUs';
values.csrf = $('meta[name="csrf"]').attr("content");

var form = $("form");

var name_input = form.find('input[name="name"]'),
    email_input = form.find('input[name="email"]'),
    contact_number_input = form.find('input[name="contact_number"]'),
    contact_by_phone_input = form.find('input[name="contact_by_phone"]'),
    subject_input = form.find('input[name="subject"]'),
    message_input = form.find('textarea[name="message"]');


form.on('submit', function(e)
{
    e.preventDefault();

    var args = $.extend(true, {}, values);
    args.method_param = 'submit_message';
    args.name = name_input.val();
    args.email = email_input.val();
    args.contact_number = contact_number_input.val();
    args.contact_by_phone = (contact_by_phone_input.is(':checked')) ? 1 : 0;
    args.subject = subject_input.val();
    args.message = message_input.val();
    args.captcha = grecaptcha.getResponse();

    __ajax_submit(args, null, form_submit_complete);

    grecaptcha.reset();
});

function form_submit_complete(msg)
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

    __reset_form(form);

    opts.is_error = false;
    opts.title = 'Message Sent!';
    opts.message = '<p>Your message has been sent successfully!</p>' +
        '<p>A DataBiz Solutions representative will be in touch as soon as possible.</p>';

    __display_dialog(opts);
    return false;
}