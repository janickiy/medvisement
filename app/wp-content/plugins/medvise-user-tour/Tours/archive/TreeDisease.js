(function ($) {
// todo сбрасывать состояние (если юзер открыл что-то до старта инструкции) или сделать прелоадер

// При загрузке нод - проставляем классы
//todo по хорошему, на шаге с древом нужно ждать, пока оно загрузится. Но из-за колва шагов успевает итак загрузиться
    var medtaxtree_loaded = false;
    var tree;
    $(window).on('MedTaxTree.loaded', function (e) {

        // -- Инициализируем элементы по древу
        tree = $("#medvise-tax-tree").fancytree("getTree");
        var found_node;

        // Первая нода, если какая-то не будет найдена
        const first_node = tree.findFirst((node) => {
            return node.children !== null
        });

        // Нода без ссылки и с дочерними элементами
        found_node = tree.findFirst((node) => {
            return node.children !== null && node.data.url === ''
        });
        if (found_node === null) {
            first_node.data.tour_classes += 'tour_tree-q ';
        } else {
            found_node.data.tour_classes += 'tour_tree-q ';
        }

        // Нода без дочерних элементов
        found_node = tree.findFirst((node) => {
            return node.children === null && node.data.url === '' && node.getLevel() > 5
        });
        if (found_node === null) {
            first_node.data.tour_classes += 'tour_tree-w ';
        } else {
            found_node.data.tour_classes += 'tour_tree-w ';
        }

        // Нода без дочерних элементов и ссылка
        found_node = tree.findFirst((node) => {
            return node.children === null && node.data.url !== ''
        });
        if (found_node === null) {
            first_node.data.tour_classes += 'tour_tree-e ';
        } else {
            found_node.data.tour_classes += 'tour_tree-e ';
        }

        // Нода с дочерними элементами и ссылка
        found_node = tree.findFirst((node) => {
            return node.children !== null && node.data.url !== ''
        });
        if (found_node === null) {
            first_node.data.tour_classes += 'tour_tree-r ';
        } else {
            found_node.data.tour_classes += 'tour_tree-r ';
        }

        // Обновляем видимые ноды сразу
        tree.render(true);
        medtaxtree_loaded = true;
    });

// Выбор меню мобильное или десктоп
    const is_mobile_menu = $(window).width() < 1024;
    var fastsearch_link_el = '#menu-item-1306';
    if (is_mobile_menu) {
        fastsearch_link_el = '.mainnav_canvas .menu-item-1306';
    }

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
                element: '#disease-tree',
                popover: {
                    description: 'Для просмотра древа заболеваний, нажмите здесь для его раскрытия.',
                    align: 'center',
                    side: 'bottom',
                    showButtons: ['next', 'close'],
                    onNextClick: () => {
                        $('#disease-tree').removeAttr('open');

                        setTimeout(function () {
                            driverObj.moveNext();
                        }, 60);
                    }
                }
            },
            {
                element: '#disease-tree',
                popover: {
                    description: 'Древо - это структура, которая поможет соотнести синдромы с конкретными заболеваниями.',
                    align: 'center',
                    side: 'bottom',
                    onNextClick: () => {
                        $('#disease-tree').attr('open', 'open');

                        setTimeout(function () {
                            driverObj.moveNext();
                        }, 60);
                    }
                }
            },
            {
                element: '.fancytree-container_search',
                popover: {
                    description:
                        'При вводе запроса в данную поисковую строку, будут показаны соответствующие элементы древа, ' +
                        'совпадающие с запросом. Не обязательно вводить запрос целиком.',
                    align: 'center',
                    side: 'bottom',
                    onNextClick: () => {
                        setTimeout(function () {
                            driverObj.moveNext();
                        }, 60);
                    }
                }
            },
            {
                element: '#medvise-tax-tree',
                popover: {
                    description:
                        'Древо не предзназначено для поиска статей, поскольку не все статьи прикреплены ' +
                        'к нему и не ко всем пунктам древа целесообразно делать отдельные статьи.',
                    align: 'center',
                    side: 'bottom',
                },
                onHighlightStarted: function (el, step, options) {
                    // Скрываем меню на мобилках
                    $('#header').find('.canvas-nav-wrap').removeClass('active');
                }
            },
            {
                element: fastsearch_link_el,
                popover: {
                    description: 'Для поиска статей воспользуйтесь разделом «Быстрый поиск» в меню.',
                    align: 'center',
                    side: 'bottom',
                    onNextClick: () => {
                        // Раскрываем ноду для следующего пункта
                        let found_node = tree.findFirst((node) => {
                            return node.data.tour_classes.includes('tour_tree-q');
                        });
                        found_node.makeVisible({
                            noAnimation: true,
                            scrollIntoView: false
                        }).done(function () {
                            setTimeout(function () {
                                driverObj.moveNext();
                            }, 120);
                        });
                    }
                },
                onHighlightStarted: function (el, step, options) {
                    // Раскрываем меню на мобилках
                    if (is_mobile_menu) {
                        $('#header').find('.canvas-nav-wrap').addClass('active');
                    }
                }
            },
            {
                element: '.tour_tree-q',
                popover: {
                    description:
                        'Если кликнуть на строку, в начале которой изображена папка, то произойдёт её раскрытие, ' +
                        'то есть можно будет посмотреть содержимое папки. <br>' +
                        'При повторном нажатии папка закроется.',
                    align: 'center',
                    side: 'bottom',
                    onNextClick: () => {
                        let found_node = tree.findFirst((node) => {
                            return node.data.tour_classes.includes('tour_tree-e');
                        });
                        found_node.makeVisible({
                            noAnimation: true,
                            scrollIntoView: false
                        }).done(function () {
                            setTimeout(function () {
                                driverObj.moveNext();
                            }, 120);
                        });
                    }
                },
                onHighlightStarted: function (el, step, options) {
                    // Скрываем меню на мобилках
                    $('#header').find('.canvas-nav-wrap').removeClass('active');
                }
            },
            {
                element: '.tour_tree-e',
                popover: {
                    description:
                        'Если в начале строки стоит значок документа, значит при нажатии на неë, ' +
                        'вы перейдёте на статью с соответствующим названием.',
                    align: 'center',
                    side: 'bottom',
                    onNextClick: () => {
                        // Раскрываем ноду для следующего пункта
                        let found_node = tree.findFirst((node) => {
                            return node.data.tour_classes.includes('tour_tree-w');
                        });
                        found_node.makeVisible({
                            noAnimation: true,
                            scrollIntoView: false
                        }).done(function () {
                            setTimeout(function () {
                                driverObj.moveNext();
                            }, 120);
                        });
                    }
                },
            },
            {
                element: '.tour_tree-w',
                popover: {
                    description: 'Если в строке со значком документа в начале, текст менее яркий, ' +
                        'чем обычно, значит к этому пункту статья не прикреплена.<br>' +
                        'Но, возможно, статья с таким названием у нас есть. ' +
                        'Попробуйте её поискать в разделе "Быстрый поиск".',
                    align: 'center',
                    side: 'bottom',
                }
            },
        ],

        onDestroyStarted: () => {
            if (confirm("Вы точно хотите пропустить инструкцию по сайту?")) {
                driverObj.destroy();
            }
        },

        onDestroyed: function (element, step, options) {
            $(window).trigger('MedUserTour.next');
        },
    });

    driverObj.drive();
})(jQuery);