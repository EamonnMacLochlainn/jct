$('.faqs dt').on('click', function()
{
    var trigger = $(this),
        icon = trigger.find('i'),
        notesCtn = trigger.next();

    if(notesCtn.is(':visible'))
    {
        notesCtn.slideUp('fast', function()
        {
            icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
    }
    else
    {
        notesCtn.slideDown('fast', function()
        {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
    }

});