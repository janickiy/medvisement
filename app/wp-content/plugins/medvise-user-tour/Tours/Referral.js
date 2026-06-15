(function ($) {

    // Выбор меню мобильное или десктоп
    const is_mobile_menu = $(window).width() < 1024;

    // Раскрываем меню на мобилках
    if (is_mobile_menu) {
        $('#header').find('.canvas-nav-wrap').addClass('active');
    }

    // Инициализируем инструкцию
    const driver = window.driver.js.driver;

    const driverObj = driver({
        smoothScroll: true, // Скролл
        overlayOpacity: 0.2, // Прозрачность тени
        stagePadding: 0, // Отступы подсветки
        stageRadius: 0, // Скругление подсветки
        popoverOffset: 10,
        showProgress: false,
        nextBtnText: 'Далее',
        prevBtnText: 'Назад',
        doneBtnText: 'Всё понятно!',
        steps: [
            {
                element: '#userMenuButton',
                popover: {
                    description:
                        'Теперь вы можете участвовать в партнерской программе, получая за это бонусы.<br>' +
                        'Панель партнера всегда находится в вашем профиле.<br> ' +
                        'Вы можете сразу перейти по <a href="/affiliate-dashboard/rules/" target="_blank">ссылке</a> и ознакомиться с правилами.',
                    align: 'center',
                    side: 'bottom',
                    showButtons: ['next', 'close']
                },
            },
        ],

        onDestroyStarted: () => {
            if (!driverObj.hasNextStep() || confirm("Вы точно хотите пропустить инструкцию по сайту?")) {
                driverObj.destroy();
            }
        },

        onDestroyed: function (element, step, options) {
            $(window).trigger('MedUserTour.next');
        },
    });

    driverObj.drive();
})(jQuery);