/**
 * Created by Eamonn on 26/06/2017.
 */


// SOME PRE-REQUISITE FUNCTIONS

function dom_default_font_size_px()
{
    var html = document.getElementsByTagName('html')[0];
    return parseInt(window.getComputedStyle( html )['fontSize']);
}





// CORE VALUES FOR USE THROUGHOUT APPLICATION

var __core = {};

__core.locale = $('meta[name="locale"]').attr('content');
__core.installation_dir = $('meta[name="_inst_dir"]').attr('content');
__core.root_url = (__core.installation_dir !== '') ? window.location.origin + '/' + __core.installation_dir : window.location.origin;

__core.dom_default_font_size_px = dom_default_font_size_px();
__core.num_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];


var __localisation = {};

__localisation.month_names = {};
__localisation.month_names.en_GB = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
__localisation.month_names.ga_IE = ['Eanáir', 'Feabhra', 'Márta', 'Aibreán', 'Bealtaine', 'Meitheamh', 'Iúil', 'Lúnasa', 'Meán Fómhair', 'Deireadh Fómhair', 'Samhain', 'Nollaig'];

__localisation.day_names = {};
__localisation.day_names.en_GB = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
__localisation.day_names.ga_IE = ['Dé Domhnaigh', 'Dé Luain', 'Dé Máirt', 'Dé Céadaoin', 'Déardaoin', 'Dé hAoine', 'Dé Sathairn'];


function __set_viewport_values()
{
    __core.viewport_width = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
    __core.viewport_width_inner = Math.floor( ( __core.viewport_width / 100 ) * 90);
    __core.viewport_height = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
    __core.viewport_height_inner = Math.floor( ( __core.viewport_height / 100 ) * 90);
}

__set_viewport_values();
window.addEventListener("orientationchange", function()
{
    __set_viewport_values();
}, false);


var $_GET = {};

function __set_GET_values()
{
    document.location.search.replace(/\??(?:([^=]+)=([^&]*)&?)/g, function () {
        function decode(s) {
            return decodeURIComponent(s.split("+").join(" "));
        }
        $_GET[decode(arguments[1])] = decode(arguments[2]);
    });
}


var _ajax_values = {};
_ajax_values.app_slug = 'site';
_ajax_values.org_section = null;
_ajax_values.user_module = null;
_ajax_values.model_title = 'Home';
_ajax_values.method_title = 'index';


// POPUP DIALOG OPTIONS


var _dialog_options = {};

_dialog_options.is_error = true;
_dialog_options.maxWidth = __core.viewport_width_inner;
_dialog_options.maxHeight = __core.viewport_height_inner;
_dialog_options.height = '';
_dialog_options.modal = true;

_dialog_options.title = 'An Error Occurred.';
_dialog_options.message = '<p>An unexpected error occurred. Please refresh the page and try again.</p>';
_dialog_options.buttons = [];
_dialog_options.dialogClass = '';
_dialog_options.dialog_classes = ['popup'];
_dialog_options.id = '';
_dialog_options.vertical_align = 'middle';
_dialog_options.autoOpen = true;

__set_dialog_dimensions();

function __set_dialog_dimensions()
{
    var _dialog_default_width = (__core.viewport_width_inner < 400) ? __core.viewport_width_inner : 400,
        _dialog_default_height = (__core.viewport_height_inner < 250) ? __core.viewport_height_inner : 250;

    _dialog_options.width = _dialog_default_width;
    _dialog_options.minWidth = _dialog_default_width;
    _dialog_options.minHeight = _dialog_default_height;
}

// the default function to display a dialog to the user.
function __display_dialog(opts)
{
    opts = (typeof opts !== 'object') ? _dialog_options : opts;

    // normalise measurements
    var max_width = __get_pixel_value(String(opts.maxWidth)),
        width = __get_pixel_value(String(opts.width)),
        set_height = (opts.height !== '') ? __get_pixel_value(String(opts.height)) : 'auto'; //(_window_height_inner / 2)

    //ensure no window overflow
    opts.maxWidth = (max_width > __core.viewport_width_inner) ? __core.viewport_width_inner : max_width;
    opts.width = (width > __core.viewport_width_inner) ? __core.viewport_width_inner : (width > max_width) ? max_width : width;
    opts.height = set_height;

    // get classes as str
    var class_str = '';

    if(opts.is_error)
    {
        opts.dialog_classes.push('popup-error');
        opts.show = {
            effect: "bounce",
            distance: 10,
            times: 2,
            duration: 200
        };
    }
    else
    {
        opts.dialog_classes.push('popup-regular');
        opts.show = {
            effect: "fade",
            duration: 200
        };
    }

    opts.hide = {
        effect: "fade",
        duration: 200
    };

    if(opts.dialog_classes.length > 0)
        class_str = opts.dialog_classes.join(' ');

    if(class_str !== '')
        opts.dialogClass = class_str;

    if( (typeof opts.buttons === 'object') && (opts.buttons.length < 1) )
    {
        var btn_class = (opts.is_error) ? 'negative' : 'positive';
        opts.buttons = [{
            text: 'OK',
            'class': btn_class,
            click: function()
            {
                $(this).dialog('close').dialog('destroy').remove();
            }
        }];
    }


    var ctn = $('<div/>');
    ctn.addClass('popup-ctn');

    var ctn_inner = $('<div/>');
    ctn_inner.addClass('popup-content');

    ctn_inner.html(opts.message);
    ctn.append(ctn_inner);

    ctn.dialog(opts);
    if(opts.id !== '')
    {
        var popup = ctn.parents('.ui-dialog');
        popup.attr('id',opts.id);
    }

    $('.ui-dialog-titlebar-close').remove();

    if(opts.vertical_align === 'middle')
    {
        var content = $(document).find('.popup-content');
        var content_height = content.outerHeight(),
            ctn_height = $(document).find('.popup-ctn').outerHeight(),
            top_margin = ( (ctn_height - content_height) / 2 );

        top_margin = (top_margin > 0) ? top_margin : 0;

        content.css('margin-top', top_margin + 'px');
    }

}

// closing a dialog via the inserted close icon
$(document).on('click', '.ui-dialog-titlebar-close', function()
{
    var popup = $(this).parents('.ui-dialog');
    popup.remove();
});




// ORG INFO

$(document).on('click', '.org-guid-ctn-guid', function()
{
    var trigger = $(this),
        ctn = trigger.parent(),
        guid = trigger.text();

    var popup = ctn.find('> .org-details-popup');
    if(popup.length > 0)
    {
        popup.fadeOut('fast', function(){ popup.remove(); });
        return false;
    }

    var values = {};
    values.app_param = 'site';
    values.model_param = 'Login';
    values.csrf = $('meta[name="csrf"]').attr("content");
    values.method_param = 'get_org_details';
    values.guid = guid;

    $.ajax({
        cache: __ajax_options.cache,
        processData: __ajax_options.process_data,
        contentType: __ajax_options.content_type,
        data: values,
        error: function(msg)
        {
            // do nothing
            console.log(msg);
            return false;
        },
        complete: function(msg)
        {
            var r = JSON.parse(msg.responseText);

            if(r.hasOwnProperty('error'))
            {
                // no nothing
                console.log(r.error);
                return false;
            }

            popup = $('<div class="org-details-popup"/>');
            popup.append('<p class="odp-title">' + r.org_name + '</p>')
                .append('<p class="odp-guid">' + r.guid + '</p>');

            var pc = r.public_contact;
            if((pc.hasOwnProperty('landline')) && (pc.landline !== '') )
                popup.append('<p class="odp-landline">' + pc.landline + '</p>');
            if((pc.hasOwnProperty('email')) && (pc.email !== '') )
                popup.append('<p class="odp-email"><a href="mailto:' + pc.email + '">' + pc.email + '</a></p>');

            ctn.append(popup);
        }
    });
});




// SLIDE WINDOW

function __slide_window_open(header_content, body_content)
{
    var slide_window = $('body > .screen-wrap > .slide-window'),
        header = slide_window.find('> .header'),
        inner = slide_window.find('> .inner');

    header
        .empty()
        .removeAttr('style')
        .append(header_content);
    inner
        .empty()
        .removeAttr('style')
        .append(body_content);

    var h = header.outerHeight() + inner.outerHeight(),
        vh = parseFloat(String(__core.viewport_height_inner).replace('px','')),
        max = vh - header.outerHeight() - 25;

    max = max.toFixed(2);

    var css = {};
    css.height = max;
    css.maxHeight = '100%';
    if(h > max)
        css['overflow-y'] = 'scroll';

    inner
        .css(css)
        .scrollTop(0);

    slide_window.animate({
        marginRight: '27rem'
    });
}

function __slide_window_close()
{
    var slide_window = $('body > .screen-wrap > .slide-window'),
        inner = slide_window.find('> .inner');

    slide_window.animate({
        marginRight: '0'
    }, 400, function(){ inner.empty(); });
}

$(document).on('click', '.slide-window-retract', function()
{
    __slide_window_close();
});








// AJAX HANDLING


var __ajax_options = {};
__ajax_options.block_ui = false;
__ajax_options.cache = false;
__ajax_options.process_data = true;
__ajax_options.content_type = 'application/x-www-form-urlencoded; charset=UTF-8';

var _block_ui = false;

$.ajaxSetup({
    headers : {
        'csrf': $('meta[name="csrf"]').attr('content')
    },
    type: 'post',
    cache: false,
    url: __core.root_url + '/jct_core/classes/AjaxHandler.php'
});

// block the ui while waiting for ajax response
$(document)
    .ajaxStart(function()
    {
        if(!__ajax_options.block_ui)
            return false;
    })
    .ajaxStop(function()
    {
        if(!__ajax_options.block_ui)
            return false;
    });

// wrapper function to handle ajax calls
// will take two callback functions, for success and completion
// will automatically display an error dialog for functional errors
function __ajax_submit(values, complete_callback, complete_args, error_callback, success_callback)
{
    var opts = $.extend(true, {}, _dialog_options);

    $.ajax({
        cache: __ajax_options.cache,
        processData: __ajax_options.process_data,
        contentType: __ajax_options.content_type,
        data: values,
        success: function(msg)
        {
            if( (success_callback !== '') && (typeof success_callback === 'function') )
                success_callback(msg);
        },
        error: function(msg)
        {
            if( (error_callback !== '') && (typeof error_callback === 'function') )
                error_callback(msg);
            else
            {
                $('body').find('> .ui-dialog').remove();

                opts.is_error = true;
                opts.message = msg;
                __display_dialog(opts);
            }
        },
        complete: function(msg)
        {
            if( (msg === '') || (msg === 'null') )
            {
                if( (complete_callback !== '') && (typeof complete_callback === 'function') )
                    complete_callback(msg, complete_args);

                console.log(msg);
                return false;
            }

            try
            {
                var r = JSON.parse(msg.responseText);
                if(r.hasOwnProperty('error'))
                {
                    if(r.hasOwnProperty('revalidate'))
                    {
                        opts.is_error = true;
                        opts.message = '<div class="relog"><p>' + r.error + '</p>' +
                            '<p>Please re-enter your credentials:<br/>' +
                            '(your action will continue upon logging in)</p>' +
                            '<label class="email"><span class="label-text">Email</span><input name="email" type="text" value=""/></label>' +
                            '<label class="password"><span class="label-text">Password</span><input name="password" type="password" value=""/></label></div>';
                        opts.buttons = [
                            {
                                text: 'Leave',
                                'class': 'negative',
                                click: function()
                                {
                                    $(this).dialog('close').dialog('destroy').remove();
                                    var _inst_dir = $('meta[name="_inst_dir"]').attr("content");
                                    window.location.href = '/' + _inst_dir + '/Login/logout';
                                }
                            },
                            {
                                text: 'Submit',
                                'class': 'positive',
                                click: function()
                                {
                                    var d = $(this),
                                        email = d.find('.email > input').val(),
                                        password = d.find('.password > input').val();

                                    var complete_callback_name = null;
                                    if( (complete_callback !== '') && (typeof complete_callback === 'function') )
                                    {
                                        if(Function.prototype.name === undefined){
                                            Object.defineProperty(Function.prototype,'name',{
                                                get:function(){
                                                    return /function ([^(]*)/.exec( this+"" )[1];
                                                }
                                            });
                                        }

                                        complete_callback_name = complete_callback.name;
                                    }


                                    relog_user(email, password, r.posted, r.user, complete_callback_name, complete_args);
                                    $(this).dialog('close').dialog('destroy').remove();
                                }
                            }
                        ];
                        __display_dialog(opts);
                        return false;
                    }
                }
            }
            catch(e)
            {
                console.log(msg.responseText);
                opts.is_error = true;
                opts.message = e;
                __display_dialog(opts);
                return false;
            }

            if( (complete_callback !== '') && (typeof complete_callback === 'function') )
                complete_callback(r, complete_args);

            return true;
        }
    });
}

function relog_user(email, password, args, user, complete_callback_name, complete_args)
{
    var login = {};
    login.app_param = 'site';
    login.model_param = 'Login';
    login.csrf = '';
    login.method_param = 'relog_user';
    login.email = email;
    login.password = password;
    login.org_guid = user.org_guid;
    login.role_id = user.role_id;

    delete(args.csrf);
    args.complete_callback_name = complete_callback_name;
    args.complete_args = complete_args;

    __ajax_submit(login, relog_user_complete, args)
}

function relog_user_complete(r, args)
{
    if(r.hasOwnProperty('error'))
    {
        var opts = $.extend(true, {}, _dialog_options);
        opts.title = 'Re-validation Failed';
        opts.message = '<p>' + r.error + '</p>' +
            '<p>Your action has been cancelled.</p>';

        __display_dialog(opts);
        return false;
    }

    args.csrf = r.id;

    var complete_callback,
        complete_args;

    if(args.hasOwnProperty('complete_callback_name'))
    {
        if( (args.complete_callback_name !== '') && (typeof window[args.complete_callback_name] === "function") )
        {
            complete_callback = window[args.complete_callback_name];
            delete(args.complete_callback_name);
        }
    }

    if(args.hasOwnProperty('complete_args'))
    {
        if( (args.complete_args !== '') && (typeof args.complete_callback_name === "object") )
        {
            complete_args = args.complete_args;
            delete(args.complete_args);
        }
    }

    __ajax_submit(args, complete_callback, complete_args);
}





// DATEPICKERS


function __set_datepicker(el, settings, readonly)
{
    el = $(el);

    if(typeof settings === 'undefined')
        settings = {};

    if(typeof readonly === 'undefined')
        readonly = true;

    // clear off any unused keys
    $.each(settings, function(k, v)
    {
        if(v === '')
            delete settings[k];
    });


    // set default date format to dd-mm-YYYY
    if(!settings.hasOwnProperty('dateFormat'))
        settings.dateFormat = 'dd-mm-yy';




    // set default date to now, or custom date obj
    var d = (settings.hasOwnProperty('defaultDate')) ? settings.defaultDate : new Date();
    settings.defaultDate = d;

    // set default min date to -1 year from default date
    if(!settings.hasOwnProperty('minDate'))
        settings.minDate = new Date((d.getFullYear() - 5), (d.getMonth() + 1), d.getDate());


    // set default max date to +1 year from default date
    if(!settings.hasOwnProperty('maxDate'))
        settings.maxDate = new Date((d.getFullYear() + 1), (d.getMonth() + 1), d.getDate());


    // whether to display dates in other months (non-selectable) at the start or end of the current month.
    if(!settings.hasOwnProperty('showOtherMonths'))
        settings.showOtherMonths = false;

    // whether weekend dates are selectable
    if(settings.hasOwnProperty('showWeekends'))
    {
        if(settings.showWeekends === false)
            settings.beforeShowDay = $.datepicker.noWeekends;
    }



    // callback function
    if(!settings.hasOwnProperty('onClose'))
        settings.onClose = function(){ _datepicker_active_id = 0; };



    el
        .datepicker(settings)
        .prop('readonly', readonly);
}

var _datepicker_active_id = 0,
    _datepicker_academic_dates = [];

function __datepicker_show_academic_dates(date)
{
    if(_datepicker_academic_dates.length < 1)
        return [true];

    var date_str = date.getFullYear() + '-' +  __lpad((date.getMonth() + 1), 0, 2) + '-' + __lpad(date.getDate(), 0, 2),
        show_date = (_datepicker_academic_dates.indexOf(date_str) !== -1);

    return [show_date];
}

function __draw_monthly_calendar(month_num, year, abbreviate_days)
{
    month_num = parseInt(month_num);
    year = parseInt(year);
    abbreviate_days = (abbreviate_days === true);

    var month_index = month_num - 1,
        date_obj = new Date(year,month_index,1);

    var date_is_valid = false;
    if( Object.prototype.toString.call(date_obj) === '[object Date]' )
    {
        if(!isNaN(date_obj.getTime()))
            date_is_valid = true;
    }

    if(!date_is_valid)
    {
        var resp = {};
        resp.error = 'Could not draw monthly calendar. Submitted date was not valid.';
        return resp;
    }

    var days_in_feb = ( (year % 100 !== 0) && (year % 4 === 0) || (year % 400 === 0) ) ? 29 : 28,
        num_days_in_set_month = (month_index === 1) ? days_in_feb : __core.num_days_in_month[month_index];

    var first_day_in_month = parseInt(String(date_obj.getDay())),
        calendar_content = '';

    var day_num = first_day_in_month;
    while(day_num > 0)
    {
        calendar_content += '<td class="prev-month-day"></td>';
        day_num--;
    }

    day_num = first_day_in_month;
    var i = 1,
        wm = 1; // week in month
    while(i <= num_days_in_set_month)
    {
        if (day_num > 6)
        {
            wm++;
            day_num = 0;
            calendar_content += '</tr><tr class="week">';
        }

        var ymd_str = year + '-' + __lpad(month_num, 0, 2) + '-' + __lpad(i, 0, 2);

        calendar_content += '<td class="day" data-date="' + ymd_str + '" data-dw="' + day_num + '" data-wm="' + wm + '">' + i + '</td>';
        day_num++;
        i++;
    }

    var ym_str = year + '-' + __lpad(month_num, 0, 2);
    var calendar = '<table class="month" data-date="' + ym_str + '">' +
        '<tr class="month-label-ctn">' +
        '<th class="month-label" colspan="7">' + __localisation.month_names[__core.locale][month_index] + ' ' + year + '</th>' +
        '</tr>';

    calendar+= '<tr class="day-label-ctn">';
    $.each(__localisation.day_names[__core.locale], function(i, name)
    {
        name = (abbreviate_days) ? name.substr(0,3) : name;
        calendar+= '<td class="day-label">' + name + '</td>';
    });
    calendar+= '</tr>';

    calendar+= '<tr class="week">' + calendar_content + '</tr></table>';

    return calendar;
}






// PLATFORM-WIDE UX IMPROVEMENTS, ETC.


// prevent screen scrolling on touch devices if
// trying to touch-drag an element (with appropriate
// data attribute) on the screen instead
var block_scroll = false;
$(window).on('touchstart', function(e)
{
    if(parseInt($(e.target).attr('data-block_scroll')) === 1)
        block_scroll = true;
});
$(window).on('touchend', function()
{
    block_scroll = false;
});
$(window).on('touchmove', function(e)
{
    if(block_scroll)
        e.preventDefault();
});


// update checkbox icons within an element
function __pretty_checkboxes(el)
{
    el = $(el);
    var check_inputs = el.find('input[type="checkbox"], input[type="radio"]');

    if(check_inputs.length < 1)
        return false;

    var icon_template = $('<span class="checkbox-icon" tabindex="0"/>'),
        icon_ctn = $('<span class="checkbox-icon-ctn"/>');

    $.each(check_inputs, function()
    {
        var input = $(this);
        if(input.hasClass('ms-checkbox'))
            return true;
        if(input.hasClass('switch-button'))
            return true;

        var parent = input.parent(),
            icon = icon_template.clone(),
            ctn = icon_ctn.clone();

        ctn.append(icon);
        parent.append(ctn);
    });
}

$(document).on('keypress', '.checkbox-icon', function(e)
{
    e.preventDefault();

    var trigger = $(this),
        key_code = e.which;

    if(key_code === 32)
        trigger.trigger('click');
    else if(key_code === 9)
    {
        var inputs = $(this).closest('form').find(':input');
        inputs.eq( inputs.index(this)+ 1 ).focus();
    }
});


// display a small toasting message, typically after an ajax event
function __show_feedback_toasting(type, text, delay)
{
    delay = (typeof delay === 'undefined') ? 1500 : parseInt(delay);
    var body = $('body');
    var toasting_list = body.find(' > #feedback-toasting-list');
    if(toasting_list.length < 1)
    {
        toasting_list = $('<ul id="feedback-toasting-list"/>');
        body.append(toasting_list);
    }

    var item_class = (typeof type === 'undefined') ? 'regular' : type,
        acceptable_types = ['regular','positive','negative'];
    if(acceptable_types.indexOf(type) === -1)
        item_class = 'regular';
    if((typeof text === 'undefined') || (text === ''))
        text = 'Done!';

    var item = $('<li class="' + item_class + '-toast"/>');
    item.text(text).css('display','none');
    toasting_list.prepend(item);

    var left = item.offset().left;

    item.css({left:left}).show('bounce', 500).delay(delay).animate({
        marginLeft: '26rem',
        opacity: 0.2
    }, 1000, function() {
        item.remove();
    });
}

$(document).on('click', '#feedback-toasting-list > li', function()
{
    $(this).fadeOut('fast', function(){ $(this).remove() });
});







// HELPER FUNCTIONS

function __delay_function(callback, ms)
{
    var timer = 0;
    return function()
    {
        var context = this, args = arguments;
        clearTimeout(timer);

        timer = setTimeout(function ()
        {
            callback.apply(context, args);
        }, ms || 0);
    };
}

function __update_list_striping(list)
{
    var n = 1;
    $.each($(list).find('> li'), function()
    {
        var item = $(this);
        item.removeClass('odd').removeClass('even');
        if(!item.is(':visible'))
            return true;

        var cl = (n%2) ? 'odd' : 'even';
        item.addClass(cl);
        n++;
    });
}

// function to return pixel value of supplied measurement string ('1rem' => 16, '10%' => 50)
function __get_pixel_value(str)
{
    var is_percentage = (str.indexOf('%') !== -1),
        is_rem = (str.indexOf('rem') !== -1);

    var float_value = parseFloat( str.replace(/[^0-9.]/g, '') );

    if( (!is_percentage) && (!is_rem) )
        return Math.round(float_value);

    if(is_percentage)
        return Math.round( Math.floor((float_value / 100) * __core.viewport_width) );

    return Math.round( Math.floor(__core.dom_default_font_size_px * float_value) );
}

// function to return a string in title case
function __to_title_case(str) {
    return str.replace(/(?:^|\s)\w/g, function(match) {
        return match.toUpperCase();
    });
}

// function to strip all whitespace from a string
function __strip_all_whitespace(str)
{
    if(str === 'undefined')
        return '';

    return String(str).replace(/\s+/g, '');
}

// retrieve url query string parameters
function __get_parameter(name, url)
{
    if(!url)
        url = window.location.href;

    name = name.replace(/[\[\]]/g, "\\$&");

    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);

    if(!results)
        return null;
    if(!results[2])
        return '';

    return decodeURIComponent(results[2].replace(/\+/g, " "));
}

// reset_form empties all inputs, returns all
// selects to their first option, and
// un-checks all checkbox and radio inputs.
// Ignores any buttons
function __reset_form(form)
{
    var inputs = form.find(':input:not(button)');
    $.each(inputs, function()
    {
        var input = $(this),
            has_set_value = (input.attr('data-set')),
            tag_name = input.prop('tagName').toLowerCase();

        var set_value;
        switch(tag_name)
        {
            case('select'):
                set_value = (has_set_value) ? input.attr('data-set') : input.find('> option:first-child').val();
                input.val(set_value);
                break;
            case('textarea'):
                set_value = (has_set_value) ? input.attr('data-set') : '';
                input.val('');
                break;

            default:

                var type = input.attr('type').toLowerCase();
                switch(type)
                {
                    case('checkbox'):
                    case('radio'):
                        set_value = (has_set_value) ? (parseInt(input.attr('data-set')) > 0) : false;
                        input.prop('checked', set_value);
                        break;
                    default:
                        set_value = (has_set_value) ? input.attr('data-set') : '';
                        input.val(set_value);
                        break;
                }
                break;
        }
    });
}

function __chunk_array(array, num_chunks, balanced)
{
    if (num_chunks < 2)
        return [array];

    var len = array.length,
        out = [],
        i = 0,
        size;

    if (len % num_chunks === 0)
    {
        size = Math.floor(len / num_chunks);
        while(i < len)
            out.push(array.slice(i, i += size));
    }
    else if(balanced)
    {
        while(i < len)
        {
            size = Math.ceil((len - i) / num_chunks--);
            out.push(array.slice(i, i += size));
        }
    }
    else
    {
        num_chunks--;
        size = Math.floor(len / num_chunks);
        if (len % size === 0)
            size--;
        while(i < size * num_chunks)
            out.push(array.slice(i, i += size));

        out.push(array.slice(size * num_chunks));
    }

    return out;
}

function __lpad(str, char, max)
{
    str = str.toString();
    return str.length < max ? __lpad(char + '' + str, max) : str;
}

function __array_min(arr)
{
    var len = arr.length, min = Infinity;
    while(len--) {
        if(arr[len] < min)
            min = arr[len];
    }
    return min;
}

function __array_max(arr)
{
    var len = arr.length, max = -Infinity;
    while(len--) {
        if(arr[len] > max)
            max = arr[len];
    }
    return max;
}

function __count_in_array(array, what)
{
    var count = 0;
    for(var i = 0; i < array.length; i++)
    {
        if(array[i] === what)
            count++
    }
    return count;
}

function __number_to_string(n)
{
    var nums_special = ['zeroth','first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'eleventh', 'twelfth', 'thirteenth', 'fourteenth', 'fifteenth', 'sixteenth', 'seventeenth', 'eighteenth', 'nineteenth'];
    var nums_deca = ['twent', 'thirt', 'fort', 'fift', 'sixt', 'sevent', 'eight', 'ninet'];

    if(n < 20)
        return nums_special[n];

    if(n%10 === 0)
        return nums_deca[Math.floor(n/10)-2] + 'ieth';

    return nums_deca[Math.floor(n/10)-2] + 'y-' + nums_special[n%10];
}

$.fn.pop = function() {
    var top = this.get(-1);
    this.splice(this.length-1,1);
    return top;
};

$.fn.shift = function() {
    var bottom = this.get(0);
    this.splice(0,1);
    return bottom;
};

function __get_date_object_from_time_wrap(wrap_el, date_obj)
{
    date_obj = (typeof date_obj === 'object') ? date_obj : new Date();

    var h_input = wrap_el.find('select[name$="_hour"]'),
        m_input = wrap_el.find('select[name$="_minute"]');

    var h = __lpad(parseInt(h_input.val()), 0, 2),
        m = __lpad(parseInt(m_input.val()), 0, 2);

    return new Date(date_obj.getFullYear(), date_obj.getMonth(), date_obj.getDate(), h, m, 0);
}

(function ($) {
    $.fn.__get_dimensions_of_hidden = function (include_margin)
    {
        var el = this,
            props = { position: 'absolute', visibility: 'hidden', display: 'block' },
            dim = { width: 0, height: 0, innerWidth: 0, innerHeight: 0, outerWidth: 0, outerHeight: 0 },
            hidden_parents = el.parents().addBack().not(':visible'),
            margin_included = (include_margin == null) ? false : include_margin;

        var old_properties = [];
        hidden_parents.each(function ()
        {
            var old = {};
            for (var name in props)
            {
                old[name] = this.style[name];
                this.style[name] = props[name];
            }
            old_properties.push(old);
        });

        dim.width = el.width();
        dim.outerWidth = el.outerWidth(margin_included);
        dim.innerWidth = el.innerWidth();
        dim.height = el.height();
        dim.innerHeight = el.innerHeight();
        dim.outerHeight = el.outerHeight(margin_included);
        dim.offset = el.offset();

        hidden_parents.each(function (i)
        {
            var old = old_properties[i];
            for (var name in props) {
                this.style[name] = old[name];
            }
        });

        return dim;
    }
}(jQuery));

function ucwords (str) {
    return (str + '').replace(/^([a-z])|\s+([a-z])/g, function ($1) {
        return $1.toUpperCase();
    });
}













// responsive global navigation


// grab some global elements in mem
var global_nav_container = $('.global-nav'),
    global_nav_list = global_nav_container.find('> ul'),
    apps_link = global_nav_list.find('> .apps-link'),
    apps_bubble = apps_link.find('> .speech-bubble'),
    global_sub_nav_trigger = global_nav_container.find('.global-sub-nav-trigger'),
    global_sub_nav_list = global_sub_nav_trigger.parent().find('> ul');

var global_nav_list_height = global_nav_list.outerHeight(),
    global_nav_list_neg_height = 0 - global_nav_list_height;

if(__core.viewport_width < 800)
    global_nav_list.css({ top: global_nav_list_neg_height + 'px' });

global_nav_list.css({
    'visibility':'visible'
});

$(document).on('click', '.global-nav-trigger', function()
{
    var visible = (global_nav_list.css('top') !== global_nav_list_neg_height + 'px');

    if(visible)
    {
        if(global_sub_nav_list.is(':visible'))
            global_sub_nav_list.toggle('drop', { direction: 'up' });

        if(apps_bubble.is(':visible'))
            apps_bubble.hide();

        global_nav_list.animate({ top: global_nav_list_neg_height + 'px' });
        return true;
    }

    global_nav_list.animate({ top: 0 }, function()
    {
        apps_bubble.fadeIn('fast');
    });
    return true;
});

global_sub_nav_trigger.on('click', function()
{
    global_sub_nav_list.toggle('drop', { direction: 'up' });
});







// app navigation

/*var app_nav_ctn = $('.app-nav-ctn'),
    app_nav_trigger = app_nav_ctn.find('> .app-nav-sidebar > .app-nav-trigger'),
    app_nav_window = app_nav_ctn.find('> .app-nav-window'),
    app_nav_window_width = app_nav_window.outerWidth();

app_nav_trigger.click(function(e)
{
    e.preventDefault();

    var visible = (app_nav_window.css('left') !== '-' + app_nav_window_width + 'px');

    if(visible)
    {
        app_nav_window.animate({ left: '-18rem' }, function()
        {
            app_nav_window.find('> ul.app-nav:not(.current-app-nav)').hide();
            app_nav_window.find('> .current-app-nav').show();
        });
        return;
    }

    app_nav_window.find('> ul.app-nav:not(.current-app-nav)').hide();
    app_nav_window.find('> .current-app-nav').show();
    app_nav_window.animate({ left: 0 });
});


function __update_app_nav_dimensions()
{
    var screen_footer = $('body > .screen-footer'),
        screen_wrap = $('body > .screen-wrap');

    var footer_height = screen_footer.height();

    screen_wrap.css({
        'padding-bottom' : footer_height + 'px',
        'margin-bottom' : '-' + footer_height + 'px'
    });
    app_nav_ctn.css('bottom', footer_height + 'px');
}

*/

var app_nav_ctn = $('.app-nav-ctn'),
    app_nav_window = app_nav_ctn.find('> .app-nav-window'),
    app_nav_window_inner = app_nav_window.find('> .app-nav-window-inner'),
    app_nav_window_width = app_nav_window.outerWidth();

$(document).on('click', '.app-nav-option-trigger', function()
{
    var trigger = $(this),
        app_slug = trigger.attr('data-slug');

    if(trigger.hasClass('open'))
    {
        trigger.removeClass('open');
        app_nav_window.animate({ left: '-18rem' });
        return false;
    }

    var values = {};
    values.app_param = 'dashboard';
    values.model_param = 'Menu';
    values.csrf = $('meta[name="csrf"]').attr("content");
    values.app_slug = app_slug;
    values.method_param = 'get_app_menu';

    __ajax_submit(values, show_menu, trigger)
});

function show_menu(r, trigger)
{
    trigger = $(trigger);

    if(r.hasOwnProperty('error'))
    {
        var e = r.error;
        switch(e)
        {
            case('org_not_subscribed'):
            case('user_not_allowed'):
                trigger.effect('bounce','slow');
                break;
            case('no_internal_navigation'):
                window.location.href = __core.root_url + '/' + trigger.attr('data-slug');
                break;
            default:
                var opts = $.extend(true, {}, _dialog_options);
                opts.error = e;
                __display_dialog(opts);
                break;
        }

        return true;
    }

    if(r.hasOwnProperty('direct_link'))
    {
        var app_slug = trigger.attr('data-slug');
        window.location.href = __core.root_url + '/' + app_slug + '/Public';
        return false;
    }

    var list = trigger.closest('.app-nav-options');
    list.find('> li > span.open').removeClass('open');
    trigger.addClass('open');

    var left = app_nav_window.css('left');
    left = left.replace ( /[^\d.]/g, '' );

    if(parseInt(left) === 0)
        app_nav_window_inner.fadeOut('fast', function()
        {
            setTimeout(function()
            {
                app_nav_window_inner.html(r);
                var current_module_nav = app_nav_window_inner.find('> .app-nav.current-module-nav');
                if(current_module_nav.length === 0)
                    app_nav_window_inner.find('> .app-nav').eq(0).css('display','block');
                else
                    current_module_nav.css('display','block');
                app_nav_window_inner.fadeIn('fast');
            }, 100);
        });
    else
    {
        app_nav_window_inner.html(r);
        var current_module_nav = app_nav_window_inner.find('> .app-nav.current-module-nav');
        if(current_module_nav.length === 0)
            app_nav_window_inner.find('> .app-nav').eq(0).css('display','block');
        else
            current_module_nav.css('display','block');
        app_nav_window_inner.show();
    }


    app_nav_window.animate({ left: 0 });
    trigger.addClass('open');
}

app_nav_window.on('click', 'li[data-get]', function()
{
    var item = $(this),
        get_name = item.data('get'),
        current_list = app_nav_window_inner.find('> .app-nav').filter(':visible');

    var new_list = app_nav_window_inner.find('> .app-nav[data-layer="' + get_name + '"]');

    if(current_list.length > 0)
        current_list.fadeOut('fast', function(){ new_list.fadeIn('fast') });
    else
        new_list.fadeIn('fast');
});

$(document).mouseup(function(e)
{
    var target = $(e.target);
    if(target.closest('.app-nav-ctn').length === 0)
    {
        if(app_nav_window.length > 0)
        {
            var left = app_nav_window.css('left');
            left = left.replace ( /[^\d.]/g, '' );
            if(parseInt(left) === 0)
            {
                app_nav_window.animate({ left: '-18rem' });
                app_nav_ctn.find('.app-nav-option-trigger.open').removeClass('open');
            }
        }
    }
});


// detect and show HTML notices & warnings
var notices = $('.screen-content').find('.ds-notice');
$.each(notices, function(i, el)
{
    el = $(el);
    var title = el.attr('title'),
        content = el.data('content');

    var title_ctn = $('<p class="ds-notice-title"/>');
    title_ctn.text(title);

    var icon = '';
    if(el.hasClass('ds-warning'))
        icon = $('<i class="fal fa-exclamation-circle" />');
    if(el.hasClass('ds-info'))
        icon = $('<i class="fal fa-exclamation-circle" />');

    title_ctn.append(icon);
    var content_wrap = $('<div class="ds-notice-inner"/>');
    content_wrap.html(content);

    el.append(title_ctn).append(content_wrap);
});



$(document).ready(function()
{
    //__update_app_nav_dimensions();

    // update checkboxes
    __pretty_checkboxes($('.screen-content'));


    return true;
});



var scroll_element = $('.back-to-top'),
    height_of_global_nav = $('body > .screen-wrap > header > nav.global-nav').height();

$(document).scroll(function()
{
    var y = $(this).scrollTop();

    setTimeout(function()
    {
        // fix app-vav to viewport or screen-wrap height
        if(y >= height_of_global_nav)
            app_nav_window.css('top', 0);
        else
            app_nav_window.css('top', height_of_global_nav + 'px');


        // show back-to-top link
        var viewport_height = $(window).height(),
            height = viewport_height / 2;

        if(y > height)
        {
            if(scroll_element.is(':visible'))
                return true;
            else
                scroll_element.fadeIn();
        }
        else
        {
            if(!scroll_element.is(':visible'))
                return true;
            else
                scroll_element.fadeOut();
        }
    }, 250);
});




// switch accounts for System Admins

$('#sysadmin-user-sel').on('change', function()
{
    var guid = $(this).val(),
        org_name = $(this).find('> :selected').text();

    if(parseInt(guid) === 0)
        return false;

    var split = org_name.split(' (');
    org_name = split[0];

    var login = {};
    login.app_param = 'site';
    login.model_param = 'Login';
    login.method_param = 'switch_account';
    login.org_guid = guid;
    login.org_name = org_name;

    __ajax_submit(login, switch_account_complete)
});

function switch_account_complete(r)
{
    if(r.hasOwnProperty('error'))
    {
        var opts = $.extend(true, {}, _dialog_options);
        opts.message = r.error;
        __display_dialog(opts);

        $('#sysadmin-user-sel').val('DATABIZ');
        return false;
    }

    window.location.href = __core.root_url + '/Dashboard';
}








$(document).resize(function()
{
    __set_viewport_values();
    __set_dialog_dimensions();
});