'use strict';

jQuery(document).ready(function ($) {

    // Если запускаются инструкции - сбрасывать положение скролла
    /*history.scrollRestoration = "manual";
    $(window).on('beforeunload', function () {
        $(window).scrollTop(0);
    });*/

    if ( MedviseTours.length === 0) {
        return true;
    }

    var current_tour = 0;

    // Проверяем, если тест уже пройден - переходим сразу к следующему
    if (Cookies.get(`tour_${MedviseTours[current_tour]['name']}`) !== undefined &&
        location.search.split('tour=')[1] !== 'force'
    ) {
        $(window).trigger('MedUserTour.next');
    } else {
        MedviseTours[current_tour]['callback']();
    }

    // Переход к следующему туру
    $(window).on('MedUserTour.next', function (e) {

        // Выставляем куку пройден предыдущий тест
        Cookies.set(`tour_${MedviseTours[current_tour]['name']}`, '1', { expires: 365 })

        // Сохраняем мета для авторизованного пользователя
        if( $('body').hasClass('logged-in') ) {
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: woocommerce_params.ajax_url,
                data: {
                    'action': 'completed_tour',
                    'tour': MedviseTours[current_tour]['name'],
                }
            });
        }

        current_tour++;

        // Больше тестов нет
        if ( MedviseTours[current_tour] === undefined ) {
            return true;
        }

        // Проверяем, если тест уже пройден - переходим сразу к следующему
        if (Cookies.get(`tour_${MedviseTours[current_tour]['name']}`) !== undefined &&
            location.search.split('tour=')[1] !== 'force'
        ) {
            $(window).trigger('MedUserTour.next');
        } else {
            MedviseTours[current_tour]['callback']();
        }
    });

});

function waitForElm(selector) {
    return new Promise(resolve => {
        if (document.querySelector(selector)) {
            return resolve(document.querySelector(selector));
        }

        const observer = new MutationObserver(mutations => {
            if (document.querySelector(selector)) {
                observer.disconnect();
                resolve(document.querySelector(selector));
            }
        });

        // If you get "parameter 1 is not of type 'Node'" error, see https://stackoverflow.com/a/77855838/492336
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
}
