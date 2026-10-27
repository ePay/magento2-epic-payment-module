define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var delays = [1000, 1000, 1000, 1000, 1000, 2000, 2000, 3000];
        var timeout = 60000;
        var startedAt = Date.now();
        var delayIndex = 0;
        var timeoutMessage = $(element).find('[data-role="payment-timeout-message"]');

        function showTimeoutMessage() {
            timeoutMessage.removeAttr('hidden');
        }

        function scheduleNextPoll() {
            var elapsed = Date.now() - startedAt;
            var delay = delays[delayIndex] || 5000;

            delayIndex += 1;

            if (elapsed >= timeout) {
                showTimeoutMessage();
                return;
            }

            window.setTimeout(poll, Math.min(delay, timeout - elapsed));
        }

        function poll() {
            if (Date.now() - startedAt >= timeout) {
                showTimeoutMessage();
                return;
            }

            $.ajax({
                url: config.statusUrl,
                type: 'GET',
                dataType: 'json',
                cache: false
            }).done(function (response) {
                if (response && response.status === 'paid') {
                    window.location.assign(config.successUrl);
                    return;
                }

                scheduleNextPoll();
            }).fail(scheduleNextPoll);
        }

        scheduleNextPoll();
    };
});
