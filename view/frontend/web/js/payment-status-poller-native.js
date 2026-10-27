(function () {
    'use strict';

    function init() {
        var element = document.querySelector('[data-epay-payment-pending]');
        if (!element) {
            return;
        }

        var statusUrl = element.getAttribute('data-status-url');
        var successUrl = element.getAttribute('data-success-url');
        var timeoutMessage = element.querySelector('[data-role="payment-timeout-message"]');
        var delays = [1000, 1000, 1000, 1000, 1000, 2000, 2000, 3000];
        var startedAt = Date.now();
        var delayIndex = 0;
        var timeout = 60000;

        function scheduleNextPoll() {
            var remaining = timeout - (Date.now() - startedAt);
            if (remaining <= 0) {
                timeoutMessage.removeAttribute('hidden');
                return;
            }

            var delay = delays[delayIndex] || 5000;
            delayIndex += 1;
            window.setTimeout(poll, Math.min(delay, remaining));
        }

        function poll() {
            if (Date.now() - startedAt >= timeout) {
                timeoutMessage.removeAttribute('hidden');
                return;
            }

            fetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {Accept: 'application/json'}
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Payment status request failed');
                }
                return response.json();
            }).then(function (result) {
                if (result && result.status === 'paid') {
                    window.location.assign(successUrl);
                    return;
                }
                scheduleNextPoll();
            }).catch(scheduleNextPoll);
        }

        scheduleNextPoll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
}());
