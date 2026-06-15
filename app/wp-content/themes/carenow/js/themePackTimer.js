; (function ($) {
    'use strict';

    /**
     * Форматирует число с ведущим нулем
     */
    function pad(num) {
        return num < 10 ? '0' + num : num;
    }

    /**
     * Склонение слов (1 день, 2 дня, 5 дней)
     */
    function plural(num, forms) {
        num = Math.abs(num) % 100;
        var n1 = num % 10;
        if (num > 10 && num < 20) return forms[2];
        if (n1 > 1 && n1 < 5) return forms[1];
        if (n1 === 1) return forms[0];
        return forms[2];
    }

    /**
     * Вычисляет разницу между текущей датой и датой истечения
     */
    function calculateTimeLeft(expiryDate) {
        var now = new Date().getTime();
        var expiry = new Date(expiryDate).getTime();
        var diff = expiry - now;

        if (diff <= 0) {
            return null;
        }

        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((diff % (1000 * 60)) / 1000);

        return { days: days, hours: hours, minutes: minutes, seconds: seconds };
    }

    function formatTimeLeft(timeLeft) {
        if (!timeLeft) {
            return 'Доступ истек';
        }

        var parts = [];

        if (timeLeft.days > 0) {
            parts.push(timeLeft.days + ' ' + plural(timeLeft.days, ['день', 'дня', 'дней']));
        }

        if (timeLeft.hours > 0 || timeLeft.days > 0) {
            parts.push(pad(timeLeft.hours) + ' ' + plural(timeLeft.hours, ['час', 'часа', 'часов']));
        }

        if (timeLeft.days === 0) {
            parts.push(pad(timeLeft.minutes) + ' ' + plural(timeLeft.minutes, ['минута', 'минуты', 'минут']));
        }

        if (timeLeft.days === 0 && timeLeft.hours === 0) {
            parts.push(pad(timeLeft.seconds) + ' ' + plural(timeLeft.seconds, ['секунда', 'секунды', 'секунд']));
        }

        return parts.join(' ');
    }

    /**
     * Инициализирует счетчик
     */
    function initTimer() {
        var $timer = $('.theme-pack-timer');

        if (!$timer.length) {
            return;
        }

        var expiryDate = $timer.data('expiry-date');
        var $countdown = $timer.find('.theme-pack-timer__countdown');

        if (!expiryDate) {
            return;
        }

        function updateTimer() {
            var timeLeft = calculateTimeLeft(expiryDate);
            var formatted = formatTimeLeft(timeLeft);
            $countdown.text(formatted);

            if (!timeLeft) {
                clearInterval(interval);
                setTimeout(function () {
                    location.reload();
                }, 2000);
            }
        }

        updateTimer();
        var interval = setInterval(updateTimer, 1000);
    }

    $(function () {
        initTimer();
    });

})(jQuery);
