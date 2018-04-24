$(document).on('click', '.product-list a', function(e)
{
    e.preventDefault();

    $('html,body').animate({
        scrollTop: $( $(this).attr('href') ).offset().top -20
    });

    return false;
});