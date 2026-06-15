jQuery(document).ready(function ($) {
    // Предохранитель от повторного выполнения скрипта.
    if ($('body').hasClass('my-quiz-builder-js-initialized')) {
        return;
    }
    $('body').addClass('my-quiz-builder-js-initialized');

    var quizApp = $('#my-quiz-builder-app');
    if (!quizApp.length) return;

    var quizIdInput = $('#quiz-id-hidden');
    var quizTitleInput = $('#quiz-title-input');
    var quizContentEditorId = 'quiz-content-editor';
    var quizBuilderForm = $('#quiz-builder-form');
    var quizMessage = $('#quiz-message');
    var questionsContainer = $('#questions-container');
    var modalityNamesContainer = $('#modality-names-container');
    var modalityResultsContainer = $('#modality-results-container');

    var quizData = quizApp.data('quiz-data') || {modalities: [], questions: [], results: []};

    // --- ФУНКЦИЯ ИНИЦИАЛИЗАЦИИ ОДИНОЧНОГО РЕДАКТОРА ---
    function initializeSingleEditor(editorId) {
        if (typeof wp === 'undefined' || typeof wp.editor === 'undefined' || typeof tinymce === 'undefined') {
            console.error('WordPress editor scripts are not loaded.');
            return;
        }
        // Надежный способ: сначала полностью удаляем старый экземпляр, если он есть
        if (tinymce.get(editorId)) {
            wp.editor.remove(editorId);
        }
        // Инициализируем новый с небольшой задержкой
        setTimeout(function () {
            wp.editor.initialize(editorId, {
                "tinymce": {
                    "theme": "modern",
                    "skin": "lightgray",
                    "language": "ru",
                    "relative_urls": false,
                    "remove_script_host": false,
                    "convert_urls": false,
                    "browser_spellcheck": true,
                    "fix_list_elements": true,
                    "entities": "38,amp,60,lt,62,gt",
                    "entity_encoding": "raw",
                    "keep_styles": false,
                    "menubar": true,
                    "branding": false,
                    "preview_styles": "font-family font-size font-weight font-style text-decoration text-transform",
                    "end_container_on_empty_block": true,
                    "wpautop": true,
                    element_format: 'html',
                    "indent": true,
                    "toolbar1": "formatselect,bold,italic,blockquote,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo,spellchecker,fontselect,fontsizeselect,outdent,indent,pastetext,removeformat,charmap,wp_more,forecolor,table,wp_help",
                    "tabfocus_elements": ":prev,:next",
                    "content_css": "https://staging1.medvisement.com/wp-includes/css/dashicons.min.css?ver=6.5.5,https://staging1.medvisement.com/wp-includes/js/tinymce/skins/wordpress/wp-content.css?ver=6.5.5",
                    "plugins": "charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview,image",
                    "external_plugins": {
                        "table": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/table/plugin.min.js",
                        "advlist": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/advlist/plugin.min.js",
                        "wptadv": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/wptadv/plugin.min.js",
                        "anchor": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/anchor/plugin.min.js",
                        "code": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/code/plugin.min.js",
                        "insertdatetime": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/insertdatetime/plugin.min.js",
                        "nonbreaking": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/nonbreaking/plugin.min.js",
                        "print": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/print/plugin.min.js",
                        "searchreplace": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/searchreplace/plugin.min.js",
                        "visualblocks": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/visualblocks/plugin.min.js",
                        "visualchars": "https://staging1.medvisement.com/wp-content/plugins/tinymce-advanced/mce/visualchars/plugin.min.js"
                    }
                },
                "quicktags": true,
                "mediaButtons": false
            });
        }, 50);
    }

    function removeWpEditor(editorId) {
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
            wp.editor.remove(editorId);
        }
    }

    // --- ФУНКЦИИ-КОНСТРУКТОРЫ HTML ---
    function buildQuestionHtml(questionData = null) {
        var questionId = (questionData && questionData.id) ? questionData.id : 'question_' + Date.now();
        var questionText = (questionData && questionData.question_text) ? questionData.question_text : '';
        var isRequired = (questionData !== null) ? questionData.is_required : true;
        var selectedModalityId = (questionData && questionData.modality_id) ? questionData.modality_id : '';
        var modalityOptionsHtml = '<option value="">-- Выберите модальность --</option>';
        (quizData.modalities || []).forEach(function (modality) {
            modalityOptionsHtml += `<option value="${modality.id}" ${selectedModalityId === modality.id ? 'selected' : ''}>${modality.name}</option>`;
        });
        return `<div class="quiz-question-item form-section" data-question-id="${questionId}"><div class="question-header"><div class="drag-handle"><span class="dashicons dashicons-move"></span></div><label for="question-text-${questionId}">Текст вопроса:</label><button type="button" class="remove-question button button-small button-danger"><span class="dashicons dashicons-trash"></span></button></div><textarea id="question-text-${questionId}" class="question-text-input">${questionText}</textarea><div class="question-modality-selector"><label for="question-modality-${questionId}">Модальность этого вопроса:</label><select id="question-modality-${questionId}" class="question-modality-select">${modalityOptionsHtml}</select></div><label class="is-required-label"><input type="checkbox" class="is-required-checkbox" ${isRequired ? 'checked' : ''}> Обязательный вопрос?</label><div class="question-answers-container"><h3>Ответы:</h3></div><button type="button" class="add-answer button button-small button-secondary">Добавить ответ</button></div>`;
    }

    function buildModalityResultHtml(resultData = null) {
        var resultId = 'result_desc_' + Date.now() + Math.floor(Math.random() * 1000); // Более уникальный ID
        var selectedModalityId = (resultData && resultData.modality_id) ? resultData.modality_id : '';
        var minScore = (resultData && typeof resultData.min_score !== 'undefined') ? resultData.min_score : 0;
        var maxScore = (resultData && typeof resultData.max_score !== 'undefined') ? resultData.max_score : '';
        var description = (resultData && resultData.description) ? resultData.description : '';
        var modalitiesOptions = '';
        (quizData.modalities || []).forEach(modality => {
            modalitiesOptions += `<option value="${modality.id}" ${selectedModalityId === modality.id ? 'selected' : ''}>${modality.name}</option>`;
        });
        return `<div class="modality-result-item form-section"><div class="result-header"><label>Результат для модальности:</label><button type="button" class="remove-result-description button button-small button-danger"><span class="dashicons dashicons-trash"></span></button></div><select class="modality-result-select">${modalitiesOptions}</select><div class="score-range-inputs"><input type="number" step="0.1" class="min-score-input" value="${minScore}" placeholder="Мин. балл"><span>-</span><input type="text" class="max-score-input" value="${maxScore}" placeholder="Макс. балл (пусто для ∞)"></div><textarea id="${resultId}" class="result-description-input">${description}</textarea></div>`;
    }

    function addAnswer(questionContainer, answerData = null) {
        var answerId = (answerData && answerData.id) ? answerData.id : 'answer_' + Date.now();
        var answerText = (answerData && answerData.answer_text) ? answerData.answer_text : '';
        var score = (answerData && typeof answerData.score !== 'undefined') ? answerData.score : 0;
        var html = `<div class="quiz-answer-item" data-answer-id="${answerId}"><div class="answer-drag-handle"><span class="dashicons dashicons-menu"></span></div><textarea class="answer-text-input" placeholder="Текст ответа...">${answerText}</textarea><div class="answer-scores"><div class="modality-score-input"><label>Балл:</label><input type="number" step="0.1" class="answer-score-value" value="${score}"></div></div><button type="button" class="remove-answer button button-small button-danger"><span class="dashicons dashicons-no-alt"></span></button></div>`;
        questionContainer.append(html);
    }

    // --- ОСНОВНАЯ ЛОГИКА ЗАГРУЗКИ ---
    function loadQuizDataToForm() {
        if (quizData && quizData.modalities) {
            quizData.modalities.forEach(m => addModalityName(m.id, m.name));
        }

        var questionsHtml = '';
        if (quizData && quizData.questions) {
            quizData.questions.forEach(q => {
                questionsHtml += buildQuestionHtml(q);
            });
        }
        var resultsHtml = '';
        if (quizData && quizData.results) {
            quizData.results.forEach(r => {
                resultsHtml += buildModalityResultHtml(r);
            });
        }

        questionsContainer.html(questionsHtml);
        modalityResultsContainer.html(resultsHtml);

        var editorsToInit = questionsContainer.find('.question-text-input').add(modalityResultsContainer.find('.result-description-input'));
        var i = 0;

        function initializeNext() {
            if (i >= editorsToInit.length) {
                setupSortableAndAnswers();
                return;
            }
            initializeSingleEditor(editorsToInit[i].id);
            i++;
            setTimeout(initializeNext, 250); // Пауза в 250мс между инициализацией
        }

        initializeNext();
    }

    function setupSortableAndAnswers() {
        questionsContainer.find('.quiz-question-item').each(function () {
            var $questionElement = $(this);
            var questionId = $questionElement.data('question-id');
            var originalQuestionData = (quizData.questions || []).find(q => q.id === questionId);

            var answersContainer = $questionElement.find('.question-answers-container');
            answersContainer.sortable({
                handle: '.answer-drag-handle', axis: 'y', placeholder: 'quiz-answer-placeholder'
            });

            if (originalQuestionData && originalQuestionData.answers) {
                originalQuestionData.answers.forEach(answer => addAnswer(answersContainer, answer));
            }
        });
        $('.answer-text-input').each(function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        questionsContainer.sortable({
            handle: '.drag-handle', axis: 'y', placeholder: 'quiz-question-placeholder', opacity: 0.8, cursor: 'move'
        });
    }

    function addModalityName(modalityId = '', modalityName = '') {
        var id = modalityId || 'modality_' + Date.now();
        var html = `<div class="modality-item form-section" data-modality-id="${id}"><input type="text" id="modality-name-${id}" class="modality-name-input" value="${modalityName}" placeholder="Введите название модальности"><button type="button" class="remove-modality-name button button-small button-danger"><span class="dashicons dashicons-trash"></span></button></div>`;
        modalityNamesContainer.append(html);
    }

    function updateModalityDropdowns() {
        var newQuizData = {modalities: []};
        modalityNamesContainer.find('.modality-item').each(function () {
            var id = $(this).data('modality-id');
            var name = $(this).find('.modality-name-input').val().trim();
            if (name) newQuizData.modalities.push({id: id, name: name});
        });
        quizData.modalities = newQuizData.modalities;
        var modalityOptionsHtml = '<option value="">-- Выберите модальность --</option>';
        quizData.modalities.forEach(function (modality) {
            modalityOptionsHtml += `<option value="${modality.id}">${modality.name}</option>`;
        });
        $('.question-modality-select, .modality-result-select').each(function () {
            var currentSelected = $(this).val();
            $(this).html(modalityOptionsHtml).val(currentSelected);
        });
    }

    function collectQuizData() {
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        var data = {modalities: [], questions: [], results: []};

        modalityNamesContainer.find('.modality-item').each(function () {
            var name = $(this).find('.modality-name-input').val().trim();
            if (name) data.modalities.push({id: $(this).data('modality-id'), name: name});
        });

        questionsContainer.find('.quiz-question-item').each(function () {

            const tinymce_textarea = $(this).find('.wp-editor-wrap .wp-editor-container > textarea').attr('id');

            const questionText = tinymce.get(tinymce_textarea).getContent();

            if (!questionText) return;

            var question = {
                id: $(this).data('question-id'),
                question_text: questionText,
                modality_id: $(this).find('.question-modality-select').val(),
                is_required: $(this).find('.is-required-checkbox').is(':checked'),
                answers: []
            };

            $(this).find('.quiz-answer-item').each(function () {
                var answerText = $(this).find('.answer-text-input').val().trim();
                if (!answerText) return;
                question.answers.push({
                    id: $(this).data('answer-id'),
                    answer_text: answerText,
                    score: parseFloat($(this).find('.answer-score-value').val()) || 0
                });
            });

            data.questions.push(question);
        });

        modalityResultsContainer.find('.modality-result-item').each(function () {

            const tinymce_textarea = $(this).find('.wp-editor-wrap .wp-editor-container > textarea').attr('id');

            const description = tinymce.get(tinymce_textarea).getContent();

            if (description) {
                data.results.push({
                    modality_id: $(this).find('.modality-result-select').val(),
                    min_score: parseFloat($(this).find('.min-score-input').val()) || 0,
                    max_score: $(this).find('.max-score-input').val().trim(),
                    description: description
                });
            }
        });

        return data;
    }

    function validateQuizData(data) {
        if (!quizTitleInput.val().trim()) {
            alert(myQuizBuilderAjax.messages.error_title_empty);
            return false;
        }
        if (data.modalities.length === 0) {
            alert(myQuizBuilderAjax.messages.error_no_modalities);
            return false;
        }
        if (data.questions.length === 0) {
            alert(myQuizBuilderAjax.messages.error_no_questions);
            return false;
        }
        for (const q of data.questions) {
            if (!q.modality_id) {
                alert(myQuizBuilderAjax.messages.error_question_no_modality + '\n\n"' + q.question_text + '"');
                return false;
            }
            if (q.answers.length === 0) {
                alert(myQuizBuilderAjax.messages.error_no_answers + '\n\n"' + q.question_text + '"');
                return false;
            }
        }
        if (data.results.length === 0) {
            alert(myQuizBuilderAjax.messages.error_no_results);
            return false;
        }
        return true;
    }

    function autoResizeTextarea() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }

    questionsContainer.on('input', '.answer-text-input', autoResizeTextarea);

    $('#add-modality-name').on('click', () => {
        addModalityName();
        updateModalityDropdowns();
    });
    modalityNamesContainer.on('click', '.remove-modality-name', function () {
        $(this).closest('.modality-item').remove();
        updateModalityDropdowns();
    });
    modalityNamesContainer.on('input', '.modality-name-input', updateModalityDropdowns);

    $('#add-question').on('click', function () {
        var questionHtml = buildQuestionHtml();
        var $questionElement = $(questionHtml);
        questionsContainer.append($questionElement);
        initializeSingleEditor($questionElement.find('.question-text-input').attr('id'));
        $questionElement.find('.question-answers-container').sortable({
            handle: '.answer-drag-handle', axis: 'y', placeholder: 'quiz-answer-placeholder'
        });
    });

    questionsContainer.on('click', '.remove-question', function () {
        var item = $(this).closest('.quiz-question-item');
        removeWpEditor(item.find('.question-text-input').attr('id'));
        item.remove();
    });

    questionsContainer.on('click', '.add-answer', function () {
        addAnswer($(this).siblings('.question-answers-container'));
    });
    questionsContainer.on('click', '.remove-answer', function () {
        $(this).closest('.quiz-answer-item').remove();
    });

    $('#add-modality-result').on('click', function () {
        var resultHtml = buildModalityResultHtml();
        var $resultElement = $(resultHtml);
        modalityResultsContainer.append($resultElement);
        initializeSingleEditor($resultElement.find('.result-description-input').attr('id'));
    });

    modalityResultsContainer.on('click', '.remove-result-description', function () {
        var item = $(this).closest('.modality-result-item');
        removeWpEditor(item.find('.result-description-input').attr('id'));
        item.remove();
    });

    quizBuilderForm.on('submit', function (e) {
        e.preventDefault();
        var actionType = $(document.activeElement).val();
        var collectedData = collectQuizData();
        if (!validateQuizData(collectedData)) return;
        var submitButtons = quizBuilderForm.find('button[type="submit"]');
        submitButtons.prop('disabled', true).css('opacity', 0.7);
        quizMessage.hide();
        $.ajax({
            url: myQuizBuilderAjax.ajax_url, type: 'POST', data: {
                action: 'my_quiz_builder_save_quiz',
                nonce: myQuizBuilderAjax.nonce,
                quiz_id: quizIdInput.val(),
                quiz_title: quizTitleInput.val(),
                quiz_data: JSON.stringify(collectedData),
                action_type: actionType
            }, success: function (response) {
                if (response.success) {
                    quizMessage.html(`<p class="success-message">${response.data.message}</p>`).show();
                    if (response.data.quiz_id && quizIdInput.val() == 0) {
                        var newUrl = window.location.href.split('?')[0] + '?quiz_id=' + response.data.quiz_id;
                        window.history.pushState({path: newUrl}, '', newUrl);
                        quizIdInput.val(response.data.quiz_id);
                    }
                    if (response.data.status) {
                        $('#quiz-current-status-hidden').val(response.data.status);
                    }
                } else {
                    quizMessage.html(`<p class="error-message">${response.data.message}</p>`).show();
                }
                $('html, body').animate({scrollTop: 0}, 'slow');
            }, error: function () {
                quizMessage.html(`<p class="error-message">Произошла критическая ошибка. Пожалуйста, обновите страницу и попробуйте снова.</p>`).show();
                $('html, body').animate({scrollTop: 0}, 'slow');
            }, complete: function () {
                submitButtons.prop('disabled', false).css('opacity', 1);
            }
        });
    });

    // --- ЗАПУСК ---
    loadQuizDataToForm();

    if (quizIdInput.val() == 0 && modalityNamesContainer.children('.modality-item').length === 0) {
        addModalityName('', 'Общие вопросы');
        updateModalityDropdowns();
    }
});