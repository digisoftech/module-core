define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        var $button = $(element);

        $button.on('click', function () {
            $button.prop('disabled', true);

            $.ajax({
                url: config.refreshUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                showLoader: true
            }).done(function (response) {
                if (response.success) {
                    window.location.reload();
                    return;
                }

                window.alert(response.message || $t('Unable to refresh license status.'));
            }).fail(function () {
                window.alert($t('Unable to refresh license status.'));
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    };
});
