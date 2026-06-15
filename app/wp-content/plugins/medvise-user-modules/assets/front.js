'use strict';

(function ($) {

    document.addEventListener("DOMContentLoaded", function (event) {
        if ($("#umTemplateModal").length === 1) {
            init_template();
        }
        if ($("#umNotesModal").length === 1) {
            init_notes();
        }
        if ($("#user-modules_timer").length === 1) {
            init_timer();
        }
    });

    /* Шаблоны START */

    function init_template() {

        const id = 'um-template-editor';

        wp.editor.initialize(id, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect | bold italic underline | bullist numlist | link, unlink | alignleft aligncenter alignright | outdent indent | forecolor backcolor',
                height : "400px",
                plugins: 'textcolor'
            },
            quicktags: false
        });

        $(document).on('click', '.user-modules_templates .user-modules_templates__item', function () {
            if ($(this).data('id') !== undefined) {
                open_template($(this).data('id'));
            }
            else {
                open_template(null);
            }
        });

        $(document).on('click', '#umTemplateModal .um-controls_save', function () {
            save_template();
        });

        $(document).on('click', '#umTemplateModal .um-controls_delete', function () {
            delete_template();
        });

        render_template();
    }

    function open_template(id = null) {

        if (id !== null) {
            $("#umTemplateModal input[name='template_id']").val(id);
            $("#umTemplateModal input[name='title']").val(window.medvise_um.template.items[id].title);
            tinymce.get("um-template-editor").setContent(window.medvise_um.template.items[id].content);

            $('#umTemplateModal .um-controls_delete').removeClass('hidden');
        }
        else {
            $("#umTemplateModal input[name='template_id']").val('');
            $("#umTemplateModal input[name='title']").val('');
            tinymce.get("um-template-editor").setContent('');

            $('#umTemplateModal .um-controls_delete').addClass('hidden');
        }

        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('umTemplateModal'));
        modal.show();
    }

    function close_template() {

        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('umTemplateModal'));
        modal.hide();
    }

    function render_template() {

        var items = '';
        $.map( window.medvise_um.template.items, function( item ) {
            items += `<button class="user-modules_templates__item" data-id="${item.id}">${item.title}</button>`;
        });

        const template = {
            'items': items,
            'new': `<button class="user-modules_templates__item user-modules_templates__add-new">Добавить шаблон</button>`
        };

        $('#user-modules_templates').html(template.items);

        if ( _.keys(window.medvise_um.template.items).length < window.medvise_um.template.limit) {
            $('#user-modules_templates').append(template.new);
        }
    }

    function save_template() {

        if ( $("#umTemplateModal input[name='title']").val().length < 3 ) {
            alert('Минимальная длина заголовка - 3 символа!');
            return false;
        }

        lock_template();

        $.ajax({
            url: window.medvise_um.ajaxurl,
            dataType: 'json',
            method: 'POST',
            async: true,
            data: {
                'action': 'medvise_um_template',
                'command': 'save',
                'post_id': window.medvise_um.post_id,
                'nonce': window.medvise_um.nonce,
                'template_id': $("#umTemplateModal input[name='template_id']").val(),
                'content': tinymce.get("um-template-editor").getContent(),
                'title': $("#umTemplateModal input[name='title']").val()
            }
        }).done(function (result) {

            unlock_template();

            if (result.success) {

                window.medvise_um.template.items[result.template_id] = {
                    "id": result.template_id,
                    "title": $("#umTemplateModal input[name='title']").val(),
                    "content": tinymce.get("um-template-editor").getContent()
                };

                render_template();
                close_template();
            }
            else {
                alert(result.msg);
            }

        }).fail(function (result) {
            console.log(result);
            alert('Ошибка сервера! Попробуйте перезагрузить страницу или напишите в техподдержку.');
        });
    }

    function delete_template() {

        lock_template();

        $.ajax({
            url: window.medvise_um.ajaxurl,
            dataType: 'json',
            method: 'POST',
            async: true,
            data: {
                'action': 'medvise_um_template',
                'command': 'delete',
                'post_id': window.medvise_um.post_id,
                'nonce': window.medvise_um.nonce,
                'template_id': $("#umTemplateModal input[name='template_id']").val()
            }
        }).done(function (result) {

            unlock_template();

            if (result.success) {
                delete window.medvise_um.template.items[$("#umTemplateModal input[name='template_id']").val()]

                render_template();
                close_template();
            }
            else {
                alert(result.msg);
            }

        }).fail(function (result) {
            console.log(result);
            alert('Ошибка сервера! Попробуйте перезагрузить страницу или напишите в техподдержку.');
        });
    }

    function lock_template() {
        $('#umTemplateModal .loader-icon').removeClass('hidden');

        $("#umTemplateModal button.btn-close").attr("disabled", true);
        $("#umTemplateModal input[name='title']").attr("disabled", true);
        $("#umTemplateModal button.um-controls_delete").attr("disabled", true);
        $("#umTemplateModal button.um-controls_save").attr("disabled", true);
    }

    function unlock_template() {
        $('#umTemplateModal .loader-icon').addClass('hidden');

        $("#umTemplateModal button.btn-close").attr("disabled", false);
        $("#umTemplateModal input[name='title']").attr("disabled", false);
        $("#umTemplateModal button.um-controls_delete").attr("disabled", false);
        $("#umTemplateModal button.um-controls_save").attr("disabled", false);
    }

    /* Шаблоны END */

    /* Заметки START */

    function init_notes() {
        const id = 'um-notes-editor';

        wp.editor.initialize(id, {
            tinymce: {
                wpautop: false,
                toolbar1: 'table tabledelete | tableprops tablerowprops tablecellprops | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol',
                toolbar2: 'bold italic underline | bullist numlist | link, unlink | alignleft aligncenter alignright | outdent indent | forecolor backcolor',
                height: "350px",
                plugins: 'table, textcolor'
            },
            quicktags: false
        });

        $(document).on('click', '.user-modules_notes__edit', function () {
            open_note();
        });

        $(document).on('click', '#umNotesModal .um-controls_copy', function () {
            insert_original_to_note();
        });

        $(document).on('click', '#umNotesModal .um-controls_save', function () {
            save_note();
        });

        $(document).on('click', '#umNotesModal .um-controls_delete', function () {
            delete_note();
        });

        render_notes();
    }

    function open_note() {

        const note_exist = window.medvise_um.notes.title.length !== 0;

        tinymce.get("um-notes-editor").setContent(window.medvise_um.notes.content);

        if (window.medvise_um.notes.replace_original) {
            $('#umNotesModal .form-check-input').prop('checked', true);
        }
        else {
            $('#umNotesModal .form-check-input').prop('checked', false);
        }

        if ( ! note_exist ) {
            $('#umNotesModal .um-controls_delete').addClass('hidden');
        }
        else {
            $('#umNotesModal .um-controls_delete').removeClass('hidden');
        }

        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('umNotesModal'));
        modal.show();
    }

    function close_note() {
        let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('umNotesModal'));
        modal.hide();
    }

    function render_notes() {

        const note_exist = window.medvise_um.notes.title.length !== 0;

        let strings = {
            'edit_text': note_exist ? 'Редактировать заметки' : 'Создать заметки'
        }

        const template = {
            'edit': `<button class="user-modules_notes__item user-modules_notes__edit">${strings.edit_text}</button>`
        }

        $('#user-modules_notes').html(template.edit);
    }

    function save_note() {

        lock_note();

        $.ajax({
            url: window.medvise_um.ajaxurl,
            dataType: 'json',
            method: 'POST',
            async: true,
            data: {
                'action': 'medvise_um_notes',
                'command': 'save',
                'post_id': window.medvise_um.post_id,
                'nonce': window.medvise_um.nonce,
                'content': tinymce.get("um-notes-editor").getContent(),
                'replace_original': Number( $('#umNotesModal .form-check-input').prop('checked') )
            }
        }).done(function (result) {

            unlock_note();

            if (result.success) {
                window.medvise_um.notes.title = 'Заметка';
                window.medvise_um.notes.content = tinymce.get("um-notes-editor").getContent();
                window.medvise_um.notes.replace_original = Number( $('#umNotesModal .form-check-input').prop('checked') );

                if ($("#umNotesModal input[name=replace_original]").prop('checked')) {
                    $('.main-post .entry-content').html(window.medvise_um.notes.content);
                    window.medvise_um.notes.replace_original = 1;
                } else {
                    location.reload();
                }

                render_notes();
                close_note();
            }
            else {
                alert(result.msg);
            }

        }).fail(function (result) {
            console.log(result);
            alert('Ошибка сервера! Попробуйте перезагрузить страницу или напишите в техподдержку.');
        });
    }

    function delete_note() {

        lock_note();

        $.ajax({
            url: window.medvise_um.ajaxurl,
            dataType: 'json',
            method: 'POST',
            async: true,
            data: {
                'action': 'medvise_um_notes',
                'command': 'delete',
                'post_id': window.medvise_um.post_id,
                'nonce': window.medvise_um.nonce
            }
        }).done(function (result) {

            unlock_note();

            if (result.success) {
                // Очищаем содержимое
                window.medvise_um.notes.content = '';
                window.medvise_um.notes.replace_original = 0;

                render_notes();
                close_note();
                location.reload();
            }
            else {
                alert(result.msg);
            }

        }).fail(function (result) {
            console.log(result);
            alert('Ошибка сервера! Попробуйте перезагрузить страницу или напишите в техподдержку.');
        });

    }

    function insert_original_to_note() {
        tinymce.get("um-notes-editor").setContent(window.medvise_um.notes.content_original);
    }

    function lock_note() {
        $('#umNotesModal .loader-icon').removeClass('hidden');

        $("#umNotesModal button.btn-close").attr("disabled", true);
        $("#umNotesModal button.um-controls_delete").attr("disabled", true);
        $("#umNotesModal input[name='replace_original']").attr("disabled", true);
        $("#umNotesModal button.um-controls_copy").attr("disabled", true);
        $("#umNotesModal button.um-controls_save").attr("disabled", true);
    }

    function unlock_note() {
        $('#umNotesModal .loader-icon').addClass('hidden');

        $("#umNotesModal button.btn-close").attr("disabled", false);
        $("#umNotesModal button.um-controls_delete").attr("disabled", false);
        $("#umNotesModal input[name='replace_original']").attr("disabled", false);
        $("#umNotesModal button.um-controls_copy").attr("disabled", false);
        $("#umNotesModal button.um-controls_save").attr("disabled", false);
    }

    /* Заметки END */

    /* Таймер START */
    function init_timer() {
        const timer_el = $('#user-modules_timer');

        if (timer_el.length) {
            var timer = new easytimer.Timer();

            $(document).on('click', '#user-modules_timer .modules_timer__start', function () {
                timer.start();

                $(this).text('Стоп');
                $(this).addClass('modules_timer__stop');
                $(this).removeClass('modules_timer__start');
            });

            $(document).on('click', '#user-modules_timer .modules_timer__stop', function () {
                timer.stop();

                $(this).text('Старт');
                $(this).addClass('modules_timer__start');
                $(this).removeClass('modules_timer__stop');
            });

            $(document).on('click', '#user-modules_timer .modules_timer__pause', function () {
                timer.pause();

                $('#user-modules_timer .modules_timer__stop').text('Старт');
                $('#user-modules_timer .modules_timer__stop').addClass('modules_timer__start');
                $('#user-modules_timer .modules_timer__stop').removeClass('modules_timer__stop');
            });

            timer.addEventListener('secondsUpdated', function (e) {
                $('#user-modules_timer .modules_timer__values').html(timer.getTimeValues().toString());
            });

            timer.addEventListener('started', function (e) {
                $('#user-modules_timer .modules_timer__values').html(timer.getTimeValues().toString());
            });

            timer.addEventListener('reset', function (e) {
                $('#user-modules_timer .modules_timer__values').html(timer.getTimeValues().toString());
            });
        }
    }

    /* Таймер END */

})(jQuery);