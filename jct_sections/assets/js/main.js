/**
 * Created by Eamonn on 26/06/2017.
 */


// SOME PRE-REQUISITE FUNCTIONS

function dom_default_font_size_px()
{
    let html = document.getElementsByTagName('html')[0];
    return parseInt(window.getComputedStyle( html )['fontSize']);
}







// CORE VALUES FOR USE THROUGHOUT APPLICATION

const __core = {};

__core.locale = $('meta[name="locale"]').attr('content');
__core.installation_dir = $('meta[name="_inst_dir"]').attr('content');
__core.root_url = (__core.installation_dir !== '') ? window.location.origin + '/' + __core.installation_dir + '/' : window.location.origin + '/';

__core.viewport_width = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
__core.viewport_width_inner = Math.floor( ( __core.viewport_width / 100 ) * 90);
__core.viewport_height = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
__core.viewport_height_inner = Math.floor( ( __core.viewport_height / 100 ) * 90);
__core.dom_default_font_size_px = dom_default_font_size_px();

__core.num_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];


const __localisation = {};

__localisation.month_names = {};
__localisation.month_names.en_GB = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
__localisation.month_names.ga_IE = ['Eanáir', 'Feabhra', 'Márta', 'Aibreán', 'Bealtaine', 'Meitheamh', 'Iúil', 'Lúnasa', 'Meán Fómhair', 'Deireadh Fómhair', 'Samhain', 'Nollaig'];

__localisation.day_names = {};
__localisation.day_names.en_GB = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
__localisation.day_names.ga_IE = ['Dé Domhnaigh', 'Dé Luain', 'Dé Máirt', 'Dé Céadaoin', 'Déardaoin', 'Dé hAoine', 'Dé Sathairn'];







// POPUP DIALOG OPTIONS


const _dialog_options = {},
    _dialog_default_width = (__core.viewport_width_inner < 400) ? __core.viewport_width_inner : 400,
    _dialog_default_height = (__core.viewport_height_inner < 250) ? __core.viewport_height_inner : 250;

_dialog_options.is_error = true;
_dialog_options.maxWidth = __core.viewport_width_inner;
_dialog_options.maxHeight = __core.viewport_height_inner;
_dialog_options.width = _dialog_default_width;
_dialog_options.height = '';
_dialog_options.minWidth = _dialog_default_width;
_dialog_options.minHeight = _dialog_default_height;
_dialog_options.modal = true;

_dialog_options.title = 'An Error Occurred.';
_dialog_options.message = '<p>An unexpected error occurred. Please refresh the page and try again.</p>';
_dialog_options.buttons = [];
_dialog_options.dialogClass = '';
_dialog_options.dialog_classes = ['popup'];
_dialog_options.id = '';
_dialog_options.vertical_align = 'middle';
_dialog_options.autoOpen = true;


// the default function to display a dialog to the user.
function __display_dialog(opts)
{
    opts = (typeof opts !== 'object') ? _dialog_options : opts;

    // normalise measurements
    let max_width = __get_pixel_value(String(opts.maxWidth)),
        width = __get_pixel_value(String(opts.width)),
        set_height = (opts.height !== '') ? __get_pixel_value(String(opts.height)) : 'auto'; //(_window_height_inner / 2)

    //ensure no window overflow
    opts.maxWidth = (max_width > __core.viewport_width_inner) ? __core.viewport_width_inner : max_width;
    opts.width = (width > __core.viewport_width_inner) ? __core.viewport_width_inner : (width > max_width) ? max_width : width;
    opts.height = set_height;

    // get classes as str
    let class_str = '';

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
        let btn_class = (opts.is_error) ? 'negative' : 'positive';
        opts.buttons = [{
            text: 'OK',
            'class': btn_class,
            click: function()
            {
                $(this).dialog('close').dialog('destroy').remove();
            }
        }];
    }


    let ctn = $('<div/>');
    ctn.addClass('popup-ctn');

    let ctn_inner = $('<div/>');
    ctn_inner.addClass('popup-content');

    ctn_inner.html(opts.message);
    ctn.append(ctn_inner);

    ctn.dialog(opts);
    if(opts.id !== '')
    {
        let popup = ctn.parents('.ui-dialog');
        popup.attr('id',opts.id);
    }

    $('.ui-dialog-titlebar-close').remove();

    if(opts.vertical_align === 'middle')
    {
        let content = $(document).find('.popup-content');
        let content_height = content.outerHeight(),
            ctn_height = $(document).find('.popup-ctn').outerHeight(),
            top_margin = ( (ctn_height - content_height) / 2 );

        top_margin = (top_margin > 0) ? top_margin : 0;

        content.css('margin-top', top_margin + 'px');
    }

}

// closing a dialog via the inserted close icon
$(document).on('click', '.ui-dialog-titlebar-close', function()
{
    let popup = $(this).parents('.ui-dialog');
    popup.remove();
});








// AJAX HANDLING


// signal whether to block the UI while waiting on ajax response
let _block_ui = false;

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
        if(!_block_ui)
            return false;

        /*let spinner = $('.ds-global-ajax-spinner');

         spinner.animateSprite({
         fps: 12,
         loop: true
         }).show();

         $('.ds-global-overlay').show();*/
    })
    .ajaxStop(function()
    {
        if(!_block_ui)
            return false;

        /*let spinner = $('.ds-global-ajax-spinner');

         spinner.animateSprite('stop').hide();
         $('.ds-global-overlay').hide();*/
    });

// wrapper function to handle ajax calls
// will take two callback functions, for success and completion
// will automatically display an error dialog for functional errors
function __ajax_submit(values, success_callback, complete_callback, error_callback, complete_args)
{
    let opts = $.extend(true, {}, _dialog_options);
    $.ajax({
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
                opts.is_error = true;
                opts.message = msg;
                __display_dialog(opts);
            }
        },
        complete: function(msg)
        {
            if( (complete_callback !== '') && (typeof complete_callback === 'function') )
                complete_callback(msg, complete_args);

            return true;
        }
    });
}

function __ajax_default_error_check(response)
{
    let opts = $.extend(true, {}, _dialog_options);
    if(response === '' || response === null)
    {
        opts.message = 'No AJAX response received.';
        __display_dialog(opts);
        return false;
    }

    if((!response.hasOwnProperty('responseText')) || response.responseText === '')
    {
        opts.message = 'No AJAX response data received.';
        __display_dialog(opts);
        return false;
    }

    let r;
    try
    {
        r = JSON.parse(response.responseText);
    }
    catch (e)
    {
        opts.message = response.responseText;
        __display_dialog(opts);
        return false;
    }

    if(r === null)
    {
        opts.message = 'A Null response was received from the server.';
        console.log(response);
        __display_dialog(opts);
        return false;
    }

    if(r.hasOwnProperty('error'))
    {
        opts.message = r.error;
        __display_dialog(opts);
        return false;
    }

    return r;
}






// DATEPICKERS


function __set_datepicker(el, settings, readonly)
{
    el = $(el);

    if(typeof settings === 'undefined')
        settings = {};

    if(typeof readonly === 'undefined')
        readonly = true;

    if(!settings.hasOwnProperty('dateFormat'))
        settings.dateFormat = 'dd-mm-yy';

    if(!settings.hasOwnProperty('minDate'))
        settings.minDate = '-1y';
    else
    {
        if(settings.minDate === '')
            delete(settings.minDate);
    }

    if(!settings.hasOwnProperty('maxDate'))
        settings.maxDate = '+1y';
    else
    {
        if(settings.maxDate === '')
            delete(settings.maxDate);
    }

    if(!settings.hasOwnProperty('showOtherMonths'))
        settings.showOtherMonths = false;

    if(!settings.hasOwnProperty('beforeShowDay'))
        settings.beforeShowDay = $.datepicker.noWeekends;
    else
    {
        if(settings.beforeShowDay === '')
            delete(settings.beforeShowDay);
    }


    if(!settings.hasOwnProperty('onClose'))
        settings.onClose = function(){ _datepicker_active_id = 0; };

    el
        .datepicker(settings)
        .prop('readonly', readonly);
}

let _datepicker_active_id = 0,
    _datepicker_academic_dates = [];
function __datepicker_show_academic_dates(date)
{
    if(_datepicker_academic_dates.length < 1)
        return [true];

    let date_str = date.getFullYear() + '-' +  __lpad((date.getMonth() + 1), 0, 2) + '-' + __lpad(date.getDate(), 0, 2),
        show_date = (_datepicker_academic_dates.indexOf(date_str) !== -1);

    return [show_date];
}

function __draw_monthly_calendar(month_num, year, abbreviate_days)
{
    month_num = parseInt(month_num);
    year = parseInt(year);
    abbreviate_days = (abbreviate_days === true);

    let month_index = month_num - 1,
        date_obj = new Date(year,month_index,1);

    let date_is_valid = false;
    if( Object.prototype.toString.call(date_obj) === '[object Date]' )
    {
        if(!isNaN(date_obj.getTime()))
            date_is_valid = true;
    }

    if(!date_is_valid)
    {
        let resp = {};
        resp.error = 'Could not draw monthly calendar. Submitted date was not valid.';
        return resp;
    }

    let days_in_feb = ( (year % 100 !== 0) && (year % 4 === 0) || (year % 400 === 0) ) ? 29 : 28,
        num_days_in_set_month = (month_index === 1) ? days_in_feb : __core.num_days_in_month[month_index];

    let first_day_in_month = parseInt(String(date_obj.getDay())),
        calendar_content = '';

    let day_num = first_day_in_month;
    while(day_num > 0)
    {
        calendar_content += '<td class="prev-month-day"></td>';
        day_num--;
    }

    day_num = first_day_in_month;
    let i = 1;
    while(i <= num_days_in_set_month)
    {
        if (day_num > 6)
        {
            day_num = 0;
            calendar_content += '</tr><tr class="week">';
        }

        let ymd_str = year + '-' + __lpad(month_num, 0, 2) + '-' + __lpad(i, 0, 2);
        calendar_content += '<td class="day" data-date="' + ymd_str + '">' + i + '</td>';
        day_num++;
        i++;
    }

    let ym_str = year + '-' + __lpad(month_num, 0, 2);
    let calendar = '<table class="month" data-date="' + ym_str + '">' +
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
let block_scroll = false;
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
    let check_inputs = el.find('input[type="checkbox"], input[type="radio"]');

    if(check_inputs.length < 1)
        return false;

    let icon_template = $('<span class="checkbox-icon" tabindex="0"/>'),
        icon_ctn = $('<span class="checkbox-icon-ctn"/>');

    $.each(check_inputs, function()
    {
        let input = $(this);
        if(input.hasClass('ms-checkbox'))
            return true;

        let parent = input.parent(),
            icon = icon_template.clone(),
            ctn = icon_ctn.clone();

        ctn.append(icon);
        parent.append(ctn);
    });
}

$(document).on('keypress', '.checkbox-icon', function(e)
{
    e.preventDefault();

    let trigger = $(this),
        key_code = e.which;

    if(key_code === 32)
        trigger.trigger('click');
    else if(key_code === 9)
    {
        let inputs = $(this).closest('form').find(':input');
        inputs.eq( inputs.index(this)+ 1 ).focus();
    }
});


// display a small toasting message, typically after an ajax event
function __show_feedback_toasting(type, text)
{
    let body = $('body');
    let toasting_list = body.find(' > #feedback-toasting-list');
    if(toasting_list.length < 1)
    {
        toasting_list = $('<ul id="feedback-toasting-list"/>');
        body.append(toasting_list);
    }

    let item_class = (typeof type === 'undefined') ? 'regular' : type,
        acceptable_types = ['regular','positive','negative'];
    if(acceptable_types.indexOf(type) === -1)
        item_class = 'regular';
    if((typeof text === 'undefined') || (text === ''))
        text = 'Done!';

    let item = $('<li class="' + item_class + '-toast"/>');
    item.text(text).css('display','none');
    toasting_list.append(item);

    item.show().delay(1500).fadeOut('fast', function(){ item.remove(); });
}

$(document).on('click', '#feedback-toasting-list > li', function()
{
    $(this).fadeOut('fast', function(){ $(this).remove() });
});







// HELPER FUNCTIONS


// function to return pixel value of supplied measurement string ('1rem' => 16, '10%' => 50)
function __get_pixel_value(str)
{
    let is_percentage = (str.indexOf('%') !== -1),
        is_rem = (str.indexOf('rem') !== -1);

    let float_value = parseFloat( str.replace(/[^0-9.]/g, '') );

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
    return str.replace(/\s+/g, '');
}

// retrieve url query string parameters
function __get_parameter(name, url)
{
    if(!url)
        url = window.location.href;

    name = name.replace(/[\[\]]/g, "\\$&");

    let regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
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
    let inputs = form.find(':input:not(button)');
    $.each(inputs, function()
    {
        let input = $(this),
            has_set_value = (input.attr('data-set')),
            tag_name = input.prop('tagName').toLowerCase();

        let set_value;
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

                let type = input.attr('type').toLowerCase();
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

    let len = array.length,
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
    let len = arr.length, min = Infinity;
    while(len--) {
        if(arr[len] < min)
            min = arr[len];
    }
    return min;
}

function __array_max(arr)
{
    let len = arr.length, max = -Infinity;
    while(len--) {
        if(arr[len] > max)
            max = arr[len];
    }
    return max;
}

function __number_to_string(n)
{
    let nums_special = ['zeroth','first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'eleventh', 'twelfth', 'thirteenth', 'fourteenth', 'fifteenth', 'sixteenth', 'seventeenth', 'eighteenth', 'nineteenth'];
    let nums_deca = ['twent', 'thirt', 'fort', 'fift', 'sixt', 'sevent', 'eight', 'ninet'];

    if(n < 20)
        return nums_special[n];

    if(n%10 === 0)
        return nums_deca[Math.floor(n/10)-2] + 'ieth';

    return nums_deca[Math.floor(n/10)-2] + 'y-' + nums_special[n%10];
}

$.fn.pop = function() {
    let top = this.get(-1);
    this.splice(this.length-1,1);
    return top;
};

$.fn.shift = function() {
    let bottom = this.get(0);
    this.splice(0,1);
    return bottom;
};














// GLOBAL NAV


// grab some global elements in mem
let global_nav_container = $('.global-nav'),
    global_nav_list = global_nav_container.find('> ul'),
    global_sub_nav_trigger = global_nav_container.find('.global-sub-nav-trigger'),
    global_sub_nav_list = global_sub_nav_trigger.parent().find('> ul');

let global_nav_list_height = global_nav_list.outerHeight();
if(__core.viewport_width < 680)
    global_nav_list.css({ top: '-' + global_nav_list_height + 'px' });

global_nav_list.css('visibility', 'visible');

$(document).on('click', '.global-nav-trigger', function()
{
    let visible = (global_nav_list.css('top') !== '-' + global_nav_list_height + 'px');

    if(visible)
    {
        if(global_sub_nav_list.is(':visible'))
            global_sub_nav_list.toggle('drop', { direction: 'up' });
        global_nav_list.animate({ top: '-' + global_nav_list_height + 'px' });
        return true;
    }

    global_nav_list.animate({ top: 0 });
    return true;
});

global_sub_nav_trigger.on('click', function()
{
    global_sub_nav_list.toggle('drop', { direction: 'up' }, function()
    {
        global_sub_nav_trigger.toggleClass('open', (global_sub_nav_list.is(':visible')));
    });
});







// section navigation
let section_nav_ctn = $('.section-nav-ctn'),
    section_nav_trigger = section_nav_ctn.find('> .section-nav-sidebar > .section-nav-trigger'),
    section_nav_window = section_nav_ctn.find('> .section-nav-window'),
    breadcrumbs = section_nav_window.find('> ul.section-nav-breadcrumbs'),
    section_nav_window_width = section_nav_window.outerWidth();

section_nav_trigger.click(function(e)
{
    e.preventDefault();

    let visible = (section_nav_window.css('left') !== '-' + section_nav_window_width + 'px'),
        closing = (visible === true);

    if(visible)
    {
        section_nav_window.animate({ left: '-18rem' });
        closing = true;
    }

    section_nav_window.find('> ul.section-nav').hide();
    breadcrumbs.hide();
    if(closing)
        return;

    let current_location = window.location.pathname;
    if(__core.installation_dir !== '')
        current_location = current_location.replace('/' + __core.installation_dir + '/', '');

    let last_char = current_location[current_location.length-1],
        first_char = current_location[0];

    let current_location_split = current_location.split('/');
    if(last_char === '/')
        current_location_split.pop();
    if(first_char === '/')
        current_location_split.shift();

    let len = current_location_split.length;
    if(len < 2)
    {
        current_location_split.push('Home');
        len++;
    }

    let current_method_name = current_location_split[current_location_split.length-1],
        current_method_name_lwr = current_method_name.toLowerCase();

    for(let i = 1; i < len; i++)
    {
        // try and find nav specific to this view / model / module / section
        // or the nearest to it (e.g. view => model, model => module, etc.)

        let token = current_location_split.join(':').toLowerCase(),
            list = section_nav_window.find('> ul[data-layer="' + token + '"]');

        if(list.length > 0)
        {
            $.each(breadcrumbs.find('> li:not(:first-child)'), function(){ $(this).hide().removeClass('visible'); });
            breadcrumbs.find('> li[data-get="' + token + '"]').show().addClass('visible');

            if(current_method_name_lwr !== 'home' && current_method_name !== '')
            {
                let last_in_token = current_location_split[current_location_split.length-1];
                if(last_in_token.toLowerCase() !== current_method_name_lwr)
                {
                    let tmp = token + ':' + current_method_name_lwr;
                    let already_appended = breadcrumbs.find('> li[data-appended="' + tmp + '"]');
                    if(already_appended.length > 0)
                        already_appended.show().addClass('visible');
                    else
                        breadcrumbs.append('<li class="visible" data-appended="' + tmp + '"><span>' + current_method_name + '</span></li>');
                }
            }

            breadcrumbs.show();
            list.show();
            section_nav_window.animate({ left: 0 });
            return true;
        }

        current_location_split.pop();
    }

    list.show();
    section_nav_window.animate({ left: 0 });
});

section_nav_window.on('click', 'li[data-get]', function()
{
    let item = $(this),
        get_name = item.data('get'),
        current_list = section_nav_window.find('.section-nav').filter(':visible');

    let new_list = section_nav_window.find('> .section-nav[data-layer="' + get_name + '"]');

    if(current_list.length > 0)
        current_list.fadeOut('fast', function(){ new_list.fadeIn('fast') });
    else
        new_list.fadeIn('fast');
});

function __update_section_nav_dimensions()
{
    let screen_footer = $('body > .screen-footer'),
        screen_wrap = $('body > .screen-wrap');

    let footer_height = screen_footer.height();

    screen_wrap.css({
        'padding-bottom' : footer_height + 'px',
        'margin-bottom' : '-' + footer_height + 'px'
    });
    section_nav_ctn.css('bottom', footer_height + 'px');
}




// ON LOAD

$(window).on('load', function()
{
    __pretty_checkboxes($('.screen-content'));
    __update_section_nav_dimensions();

    return true;
});



// ON RESIZE

$(document).resize(function()
{
    __update_section_nav_dimensions();
});




// ON SCROLL

let scroll_element = $('.back-to-top'),
    height_of_global_nav = $('body > .screen-wrap > header > nav.global-nav').height();
$(document).scroll(function()
{
    let y = $(this).scrollTop();

    // fix section-vav to viewport or screen-wrap height
    if(y >= height_of_global_nav)
        section_nav_window.css('top', 0);
    else
        section_nav_window.css('top', height_of_global_nav + 'px');


    // show back-to-top link
    let viewport_height = $(window).height(),
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
});