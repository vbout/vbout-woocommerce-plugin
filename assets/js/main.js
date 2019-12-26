var $ = jQuery;

$(function () {
    $('.custom-fields').parent().parent().prev().addClass('custom-fields-wrapper');
    $('.custom-fields').parent().parent().addClass('custom-fields-wrapper');

    var settingsTitle = $('#woocommerce_vbout-integration_apiKey').parent().parent().parent().parent().parent().parent().parent().find('h2');
    settingsTitle.append('<div class="vbout-title"></div>');
    settingsTitle.next().addClass('title-description');
});