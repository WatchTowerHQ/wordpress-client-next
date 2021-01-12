let clipboard = new ClipboardJS('.clip');

clipboard.on('success', function (e) {
    jQuery('#wht-copied').css("display", "flex");
    setTimeout(function () {
        jQuery('#wht-copied').css("display", "none");
    }, 2000);
});

jQuery('#wht-refresh-token').on('click', function (e) {
    jQuery("input[name='watchtower[access_token]']").prop('checked', true);
    jQuery('#wht-form').submit();
});

