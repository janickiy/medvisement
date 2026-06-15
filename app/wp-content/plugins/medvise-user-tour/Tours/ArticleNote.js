waitForElm('.user-modules_notes__edit').then((elm) => {
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
                    element: '.user-modules_right',
                    popover: {
                        description:
                            'В заметки вы можете вынести особо важную информацию из статьи, ' +
                            'либо справочную информацию, которой часто пользуетесь ' +
                            'и необходимо быстро её найти.',
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