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
                element: '.search-form',
                popover: {
                    description:
                        'Начните вводить поисковый запрос, это может быть диагноз или синдром. <br>' +
                        'Нет необходимости вводить запрос полностью, ' +
                        'так как результаты будут видны уже на этапе ввода текста. <br>' +
                        'Если поисковый запрос включает несколько слов, начните ввод с наиболее значимых (ключевых) слов.',
                    align: 'center',
                    side: 'bottom',
                    showButtons: ['next', 'close']
                },
                onHighlightStarted: function (el, step, options) {
                    $('#disease-list').removeAttr('open');
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