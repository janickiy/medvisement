'use strict';

(function ($) {

    if ($("#medvise-tax-tree").length == 0)
        return true;

    $("#medvise-tax-tree")
        .fancytree({
            extensions: ["childcounter", "filter", "table", "gridnav", "glyph"],
            source: {
                url:
                    "/wp-json/medvise-vt/v1/tree/" + $('#medvise-tax-tree').data('type') + '?node=' + $('#medvise-tax-tree').data('node')
            },
            strings: {
                loading: "Загрузка...",
                loadError: "Ошибка загрузки!",
                moreData: "Больше данных...",
                noData: "Нет данных.",
            },
            titlesTabbable: true, // Навигация с помощью TAB

            glyph: {
                map: {
                    _addClass: "",
                    checkbox: "fas fa-square",
                    checkboxSelected: "fas fa-check-square",
                    checkboxUnknown: "fas fa-square",
                    radio: "fas fa-circle",
                    radioSelected: "fas fa-circle",
                    radioUnknown: "fas fa-dot-circle",
                    dragHelper: "fas fa-arrow-right",
                    dropMarker: "fas fa-long-arrow-right",
                    error: "fas fa-exclamation-triangle",
                    expanderClosed: "ft ft-folder-closed",
                    expanderLazy: "ft ft-folder-closed",
                    expanderOpen: "ft ft-folder-open",
                    loading: "fas fa-spinner fa-pulse",
                    nodata: "fas fa-meh",
                    noExpander: "ft ft-document",
                    doc: "",
                    docOpen: "",
                    folder: "fas fa-folder",
                    folderOpen: "ft-folder-open"
                }
            },

            // childcounter
            childcounter: {
                deep: false,
                hideZeros: true,
                hideExpanded: true
            },

            // filter
            quicksearch: true,
            filter: {
                autoExpand: true
            },

            // gridnav
            gridnav: {
                autofocusInput: false,
                handleCursorKeys: true,
            },

            // table
            table: {
                nodeColumnIdx: 0,
                indentation: 22
            },

            enhanceTitle: function (event, data) {
                var node = data.node;
                var $nodeSpan = $(node.span);

                // Для инструкций пользователя
                if ( node.data.tour_classes ) {
                    setTimeout(function() {
                        $(node.span).closest('tr').addClass(node.data.tour_classes);
                    }, 20);
                }

                // Для пометки основной статьи
                if (node.data.article_main) {
                    $nodeSpan.addClass('article-main');
                }

                if ( 'url' in node.data && node.data.url.length > 1 ) {
                    $nodeSpan.addClass('link');
                }
                else if ( null === node.children ) {
                    var $nodeTitle = $nodeSpan.find('.fancytree-title');

                    if ( ! $nodeTitle.is("[aria-describedby]") ) {
                        $nodeTitle.attr('data-bs-toggle', 'tooltip');
                        $nodeTitle.attr('data-bs-custom-class', 'tree-tooltip');
                        $nodeTitle.attr('data-bs-placement', 'bottom');
                        $nodeTitle.attr('data-bs-title', 'К данному пункту статья не прикреплена. Перейдите в "Быстрый поиск" для поиска по названиям статей.');

                        new bootstrap.Tooltip($nodeTitle.get(0));

                        $nodeTitle.on('show.bs.tooltip', function () {

                            setTimeout(function (that) {
                                $(that).tooltip("hide");
                            }, 6000, this);
                        });
                    }

                }
            },
            click: function (event, data) {
                var node = data.node,
                    targetType = data.targetType;

                // Нажато по открывателю итак
                if ( 'expander' === targetType ) {
                    return true;
                }

                if ( null !== node.children ) {
                    if ( node.expanded === true ) {
                        node.setExpanded(false);
                    }
                    else {
                        node.setExpanded();
                    }
                }
                else if ( 'url' in node.data && node.data.url.length > 1 && null === node.children ) {
                    window.open(node.data.url, '_blank').focus();
                }

            },

            init: function () {
                $(window).trigger('MedTaxTree.loaded');
            }
        });


    const tree = $.ui.fancytree.getTree("#medvise-tax-tree");

    /* Search START*/
    var search_timeout = null;

    $("input[name=vt-search]").on("keyup", function (e) {

        var search_string = $(this).val();

        //Отмена поиска на ESC
        if (e && e.which === $.ui.keyCode.ESCAPE) {
            $("button#vtBtnResetSearch").trigger("click");
            return true;
        }

        if (search_timeout) {
            $('.fancytree-container_search__row').removeClass('is-loading');
            clearTimeout(search_timeout);
        }

        //Ниче не делаем если длина = 0
        if (search_string.length <= 1) {
            return true;
        }

        $("#medvise-tax-tree").parent('details').attr('open', true);
        $('.fancytree-container_search__row').addClass('is-loading');

        search_timeout = setTimeout(() => {
            process_search(search_string);
        }, 600);

    });

    //Сама функция поиска
    function process_search(search_string) {
        var n,
            opts = tree.getOption("filter"),
            filterFunc = tree.filterNodes;

        //Сворачиваем все
        tree.expandAll(false);

        n = filterFunc.call(tree, search_string, opts);

        $("button#vtBtnResetSearch").attr("disabled", false);

        $("span#vtMatches").text("(" + n + " совпадений)");
        $('.fancytree-container_search__row').removeClass('is-loading');
    }

    //Отмена поиска
    $("button#vtBtnResetSearch").click(function (e) {
        if (search_timeout)
            clearTimeout(search_timeout);

        $(".fancytree-container_search input[type=text]").val("");
        $("span#vtMatches").text("");
        $('.fancytree-container_search__row').removeClass('is-loading');

        tree.clearFilter();

    }).attr("disabled", true);
    /* Search END */

    // Скрываем подсказки при клике вне документа
    $(document).on('click', function (e) {
        if ( $(e.target).closest("#medvise-tax-tree").length === 0 && ! $(e.target).is('[class*="fancytree"]') ) {
            $('.tree-tooltip').tooltip("hide");
        }
    });

})(jQuery);
