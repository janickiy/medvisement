'use strict';

(function ($) {

    if ($("#vt-admin").length == 0)
        return true;

    var CLIPBOARD = null;

    $("#vt-admin")
        .fancytree({
            extensions: ["childcounter", "filter", "edit", "dnd5", "table", "gridnav", "contextMenu"],
            source: {
                url:
                    "/wp-json/medvise-vt/v1/tree/" + vt_urlParams.get('post_type')
            },
            strings: {
                loading: "Загрузка...",
                loadError: "Ошибка загрузки!",
                moreData: "Больше данных...",
                noData: "Нет данных.",
            },
            titlesTabbable: true, // Навигация с помощью TAB

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

            // edit
            edit: {
                adjustWidthOfs: 0, //padding инпута включать
                triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"],
                edit: function(event, data) {
                    // Ставим каретку в конец
                    setTimeout(function(){ data.input[0].selectionStart = data.input[0].selectionEnd = 10000; }, 0);
                }
            },

            // Тут все события изменения нод
            modifyChild: function(event, data) {

                console.log(event, data);

                // ВНИМАНИЕ - некоторые события генерируют два вызова
                // 1) childNode - модифицированная нода, node - ее родитель
                // 2) childNode = null, node - сама нода
                // По умолчанию уберем второй вариант сразу
                // у 'remove' может быть и без childNode
                if (data.childNode == null && 'remove' != data.operation) {
                    return true;
                }

                // Нода создана - вызывается 'add' с пустым title, такое пропускаем
                // Нода перемещена на другой уровень - сначала удаляется 'remove', затем создается. ID сохраняется
                if ('add' == data.operation) {
                    // Если title пустой - пропускаем (нода создана, но имя еще не задано)
                    if ( data.childNode.title.length == 0) {

                        let node_parent = data.childNode.getParent();
                        // Базовые параметры
                        data.childNode.fromDict({
                            'post_id': 0,
                            'parent': node_parent.key == 'root_1' ? '0' : node_parent.key,
                            'position': data.childNode.getIndex()
                        });
                        return true;
                    }

                    // Рекурсивно открываем все ноды
                    if (data.childNode.children !== null) {
                        let nodes_array = [];
                        nodes_array.push(data.childNode);

                        let unflatted_nodes = (unflattenNodes(data.childNode.children));

                        Array.prototype.push.apply(nodes_array, unflatted_nodes);

                        saveNodes(nodes_array);
                    }
                    else {
                        saveNodes([data.childNode]);
                    }
                }

                // Нода переименована
                if ('rename' == data.operation) {

                    // Если начинается key ноды с _ - новая, нужно обновить позиции остальных нод - сохраняем весь уровень
                    // Создание новой ноды 'add' игнорируется, т.к. title = null - сохраняем здесь
                    if ( ! Number.isInteger(data.childNode.key) && data.childNode.key.startsWith('_') ) {
                        saveNodes(data.node.getChildren())
                    }
                    else {
                        // Уже существующую ноду после переименования просто сохраняем
                        saveNodes([data.childNode]);
                    }
                }


                if ('remove' == data.operation) {

                    // childNode != null - удаляем этот элемент
                    if ( data.childNode != null )  {
                        // Если начинается с _ - то этой ноды еще нет в БД
                        if ( data.childNode.key.startsWith('_') ) {
                            return true;
                        }
                        else {
                            saveNodes([data.childNode], 'delete');
                        }

                    }
                    else {
                        /*
                        childNode == null - смотрим node, если ли children
                            1) children != null - ничего не делаем
                            2) children == null - очищаем дочерние элементы node (запрос в БД)
                        */
                        if (data.node.children != null) {
                            saveNodes([data.node], 'delete-children');
                        }
                    }

                }

                // Нода перемещена на одном уровне - 'move'
                if ('move' == data.operation) {
                    // Обновляем позиции всех нод на уровне
                    saveNodes(data.node.getChildren());
                }

            },

            // dnd5
            dnd5: {
                preventVoidMoves: true,
                preventRecursion: true,
                autoExpandMS: 400,
                dragStart: function(node, data) {
                    return true;
                },
                dragEnter: function(node, data) {
                    return true;
                },
                dragDrop: function(node, data) {
                    data.otherNode.moveTo(node, data.hitMode);
                },
            },

            // gridnav
            gridnav: {
                autofocusInput: false,
                handleCursorKeys: true,
            },

            // table
            table: {
                nodeColumnIdx: 1
            },
            renderColumns: function (event, data) {
                var node = data.node,
                    $tdList = $(node.tr).find(">td");

                $tdList.eq(0).text(node.key);

                if ('post_id' in node.data && node.data.post_id != null) {
                    $tdList.eq(2).text(node.data.post_id);
                }
            },

            //Меню
            contextMenu: {
                menu: {
                    "wp-post": {"name": "Выбрать статью", "icon": "edit"},
                    "wp-post-delete": {"name": "Удалить статью", "icon": "delete"},
                    "sep1": "---------",
                    "addSibling": {"name": "Создать рядом", "icon": "add"},
                    "addChild": {"name": "Создать внутри", "icon": "add"},
                    "sep2": "---------",
                    "copy": {"name": "Копировать"},
                    "pasteSibling": {"name": "Вставить рядом"},
                    "pasteChild": {"name": "Вставить внутри"},
                    "sep3": "---------",
                    "outdent": {"name": "На уровень выше"},
                    "indent": {"name": "На уровень ниже"},
                    "moveUp": {"name": "Переместить выше"},
                    "moveDown": {"name": "Переместить ниже"},
                    "sep4": "---------",
                    "remove": {"name": "Удалить ноду", "icon": "delete"}
                },
                actions: function (node, action, options) {
                    // Ставим задержку чтобы меню не конфликтовало с другими событиями
                    setTimeout(function () {
                        $("#vt-admin").trigger("nodeCommand", {cmd: action, node: node});
                    }, 150);
                }
            },
        })
        .on("nodeCommand", function (event, data) {
            var tree = $.ui.fancytree.getTree("#vt-admin"),
                node = data.node;

            switch (data.cmd) {
                case "moveUp":
                case "moveDown":
                case "indent":
                case "outdent":
                case "remove":
                case "addChild":
                case "addSibling":
                case "rename":
                    tree.applyCommand(data.cmd, node);
                    break;
                case "wp-post":
                    setupWordpressPost(node);
                    break;
                case "wp-post-delete":
                    // Обновляем ид поста
                    node.fromDict({'post_id': 0});
                    // Триггерим событие для сохранения
                    node.triggerModify('rename');
                    break;
                case "copy":
                    CLIPBOARD = {
                        mode: data.cmd,
                        data: node.toDict(true, function(dict, node) {
                            delete dict.key;
                            delete dict.data.id;
                        }),
                    };
                    break;
                case "pasteSibling":
                    node.appendSibling(
                        CLIPBOARD.data
                    );
                    break;
                case "pasteChild":
                    node.addChildren(
                        CLIPBOARD.data
                    ).setExpanded(true, {'noAnimation': false, 'noEvents': false});
                    break;
                default:
                    alert("Неизвестная команда: " + data.cmd);
                    return;
            }
        }).on("keydown", function (e) {
        var cmd = null;
        switch ($.ui.fancytree.eventToString(e)) {
            case "ctrl+up":
                cmd = "moveUp";
                break;
            case "ctrl+down":
                cmd = "moveDown";
                break;
            case "ctrl+right":
                cmd = "indent";
                break;
            case "ctrl+left":
                cmd = "outdent";
                break;
            case "del":
            case "meta+backspace": // mac
                cmd = "remove";
                break;
            case "alt+i":
                cmd = "addChild";
                break;
            case "alt+o":
                cmd = "addSibling";
                break;
            case "alt+p":
                cmd = "wp-post";
                break;
        }
        if (cmd) {
            $(this).trigger("nodeCommand", {cmd: cmd});
            return false;
        }
    });


    const tree = $.ui.fancytree.getTree("#vt-admin");

    /* Search START*/
    var search_timeout = null;

    $("input[name=search]").on("keyup", function (e) {

        var search_string = $(this).val();

        //Отмена поиска на ESC
        if (e && e.which === $.ui.keyCode.ESCAPE) {
            $("button#btnResetSearch").trigger("click");
            return true;
        }

        if (search_timeout)
            clearTimeout(search_timeout);

        //Ниче не делаем если длина = 0
        if (search_string.length <= 1) {
            return true;
        }

        search_timeout = setTimeout(() => {
            process_search(search_string);
        }, 600);

    }).focus();

    //Сама функция поиска
    function process_search(search_string) {
        var n,
            opts = tree.getOption("filter"),
            filterFunc = tree.filterNodes;

        //Сворачиваем все
        tree.expandAll(false);

        n = filterFunc.call(tree, search_string, opts);

        $("button#btnResetSearch").attr("disabled", false);

        $("span#matches").text("(" + n + " совпадений)");
    }

    //Отмена поиска
    $("button#btnResetSearch").click(function (e) {
        if (search_timeout)
            clearTimeout(search_timeout);
        $("input[name=search]").val("");
        $("span#matches").text("");
        tree.clearFilter();
    }).attr("disabled", true);
    /* Search END */

    /* Функции универсальные START */

    // Сохранение нового имени
    function saveNodes(nodes, flag = null) {

        var data = [];

        // Выставляем позицию и подготавливаем данные
        $.each(nodes, function (index, value) {

            let data_el = {};

            // id новых элементов начинается с _
            data_el.id = value.key;
            data_el.name = value.title;
            data_el.parent = value.parent.key == 'root_1' ? '0' : value.parent.key;
            data_el.post_id = value.data.post_id;
            data_el.position = value.getIndex();

            if (flag) {
                // delete - удаляет ноду
                // delete-children - удаляет дочерей ноды
                data_el.flag = flag;
            }

            data.push(data_el);
        });

        console.log(data);

        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            method: 'POST',
            async: false,
            data: {
                'action': 'vt_update_nodes',
                'type': vt_urlParams.get('post_type'),
                'nodes': data,
                'vt_nonce': vt_nonce
            }
        }).done(function (result) {

            console.log(result);

            // После прихода ответа - обновляем key из БД
            if (Object.keys(result.processed_nodes).length) {
                $.each(result.processed_nodes, function (old_key, new_key) {
                    if (old_key != new_key) {
                        tree.getNodeByKey(old_key).fromDict({'key': new_key.toString(), 'id': new_key});
                    }
                });
            }

        }).fail(function (result) {
            console.log(result);
            alert('Ошибка сервера при сохранении дерева! Проверьте консоль и перезагрузите страницу для актуализации данных.');
        });
    }

    // Разбирает массив нод в одну плоскость
    function unflattenNodes(nodes) {
        var flat = [];

        for (let node of nodes) {
            if (node.children !== null) {
                flat.push(node);
                flat.push(...unflattenNodes(node.children));
            } else {
                flat.push(node);
            }
        }

        return flat;
    }

    function setupWordpressPost(node) {

        var form = $('#vt-admin_setup-post');

        form.find('.node-name').html("'" + node.title + " (" + node.key + ")'");
        form.find('input[name="key"]').val(node.key);
        form.find('select[name="post_id"]').select2("val", 0);

        // Открываем модалку
        $("#vt-admin_setup-post_link").trigger("click");

        //Ставим фокус
        $('select[name="post_id"]').select2('open');
    }

    /* Функции универсальные END */


    /* Выбор поста START */
    $(document).ready(function() {

        $('select[name="post_id"]').select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                method: 'POST',
                delay: 250,
                data: function (params) {
                    return {
                        'action': 'vt_get_posts',
                        'q': params.term,
                        'type': vt_urlParams.get('post_type'),
                        'vt_nonce': vt_nonce
                    };
                },
                processResults: function (data, params) {
                    console.log(data);
                    return {
                        results: data.posts
                    };
                },
                cache: true
            },
            dropdownAutoWidth: true,
            placeholder: 'Начните вводить название статьи',
            minimumInputLength: 3
        });

        $("#vt-admin_setup-post").submit(function(e){
            e.preventDefault();

            var node_key = $(this).find('input[name="key"]').val();
            var post_id = $(this).find('select[name="post_id"]').val();

            // Обновляем ид поста
            tree.getNodeByKey(node_key).fromDict({'post_id': post_id});

            // Триггерим событие для сохранения
            tree.getNodeByKey(node_key).triggerModify('rename');

            tb_remove();
        });
    });
    /* Выбор поста END */

})(jQuery);
