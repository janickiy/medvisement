(function ($) {
// Инициализируем инструкцию
    const driver = window.driver.js.driver;

    const driverObj = driver({
        smoothScroll: true, // Скролл
        overlayOpacity: 0.2, // Прозрачность тени
        stagePadding: 0, // Отступы подсветки
        stageRadius: 0, // Скругление подсветки
        popoverOffset: 10,
        showProgress: true,
        nextBtnText: 'Далее',
        prevBtnText: 'Назад',
        doneBtnText: 'Всё понятно!',
        progressText: "шаг {{current}}/{{total}}",
        steps: [
            {
                element: '#disease-list',
                popover: {
                    description:
                        'На странице отдельной специальности, внизу находится список всех заболеваний (которых может не быть в древе). <br>' +
                        'Для раскрытия нажмите по нужному элементу.',
                    align: 'center',
                    side: 'bottom',
                    showButtons: ['next', 'close']
                },
                onHighlightStarted: function (el, step, options) {
                    $('#disease-list').removeAttr('open');
                },
            },
            {
                element: '#disease-list a',
                popover: {
                    description:
                        'Каждая статья представлена ссылкой, ведущей на отдельную страницу.'
                },
                onHighlightStarted: function (el, step, options) {
                    $('#disease-list').attr('open', 'open');
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