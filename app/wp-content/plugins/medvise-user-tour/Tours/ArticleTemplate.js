waitForElm('.user-modules_templates__add-new').then((elm) => {
    (function ($) {
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
                    element: '.user-modules_left',
                    popover: {
                        description:
                            'Здесь вы можете создать типовые шаблоны рекомендаций и быстро их найти, при необходимости. <br>' +
                            'Шаблоны прикрепляются к любой странице (статья или инструкция к препарату). ' +
                            'До 5 шаблонов на одну страницу.',
                        align: 'center',
                        side: 'bottom',
                        showButtons: ['next', 'close']
                    }
                },
                {
                    element: '.user-modules_left',
                    popover: {
                        description:
                            'Шаблоны можно найти, перейдя на страницу, где они сохранены (статья или инструкция), ' +
                            'либо в разделе «мой аккаунт», выбрав пункт ' +
                            '«<a href="/my-account/tours/" target="_blank">список шаблонов</a>».',
                        align: 'center',
                        side: 'bottom',
                        showButtons: ['next', 'close']
                    }
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
});