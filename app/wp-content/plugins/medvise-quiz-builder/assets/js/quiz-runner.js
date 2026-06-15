jQuery(document).ready(function ($) {

    const $quizForm = $('#my-quiz-form');
    const $getResultsButton = $('#get-quiz-result');

    // false = начальный режим (скролл к следующему).
    // true = режим исправления (умный скролл).
    let validationModeActive = false;

    /**
     * Находит первый неотвеченный вопрос на странице.
     * @param {boolean} onlyRequired - Искать только среди обязательных вопросов.
     * @returns {jQuery|null} - jQuery объект вопроса или null, если не найден.
     */
    function findFirstUnanswered(onlyRequired = false) {
        const selector = onlyRequired ? '.quiz-question-item[data-is-required="true"]' : '.quiz-question-item';

        const $unanswered = $(selector).filter(function () {
            return $(this).find('input[type="radio"]:checked').length === 0;
        });

        return $unanswered.length ? $unanswered.first() : null;
    }

    /**
     * Плавно скроллит к указанной цели.
     * @param {jQuery} $target - jQuery объект для скролла.
     */
    function scrollToTarget($target) {
        if ($target && $target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 80 // Отступ 80px
            }, 500);
        }
    }

    /**
     * Визуально выделяет неотвеченные обязательные вопросы.
     */
    function highlightRequiredErrors() {
        $('.quiz-question-item[data-is-required="true"]').each(function () {
            const $questionItem = $(this);
            if (!$questionItem.find('input[type="radio"]:checked').length) {
                $questionItem.addClass('question-unanswered').find('.quiz-required-message').show();
            } else {
                $questionItem.removeClass('question-unanswered').find('.quiz-required-message').hide();
            }
        });
    }

    // --- ОБРАБОТЧИКИ СОБЫТИЙ ---

    /**
     * ОБРАБОТЧИК ВЫБОРА ОТВЕТА
     */
    $quizForm.on('change', 'input[type="radio"]', function () {
        const $currentQuestion = $(this).closest('.quiz-question-item');
        const isLastQuestion = $currentQuestion.next('.quiz-question-item').length === 0;

        // Если это последний вопрос, запускаем логику кнопки "Получить результат".
        if (isLastQuestion) {
            $quizForm.trigger('submit');
            return; // Завершаем выполнение, чтобы избежать двойного скролла.
        }

        // Убираем подсветку ошибки при ответе
        $currentQuestion.removeClass('question-unanswered').find('.quiz-required-message').hide();

        if (validationModeActive) {
            // РЕЖИМ 2: "Умный скролл"

            // ПРОВЕРКА №1: Все ли ОБЯЗАТЕЛЬНЫЕ вопросы теперь отвечены?
            const hasRemainingRequired = findFirstUnanswered(true);
            if (!hasRemainingRequired) {
                // Если обязательных неотвеченных не осталось, сразу скроллим к кнопке.
                scrollToTarget($getResultsButton);
                return; // Завершаем выполнение.
            }

            // Если обязательные еще остались, продолжаем логику умного скролла.
            const currentIndex = $currentQuestion.index('.quiz-question-item');

            // Ищем неотвеченные вопросы ПОСЛЕ текущего
            const $nextUnanswered = $('.quiz-question-item').filter(function () {
                return $(this).index('.quiz-question-item') > currentIndex && $(this).find('input[type="radio"]:checked').length === 0;
            }).first();

            if ($nextUnanswered.length) {
                // Если ниже есть неотвеченный вопрос, скроллим к нему
                scrollToTarget($nextUnanswered);
            } else {
                // Если ниже ничего нет, скроллим наверх к первому обязательному
                scrollToTarget(hasRemainingRequired);
            }
        } else {
            // РЕЖИМ 1: Изначальный скролл к следующему вопросу
            const $nextQuestion = $currentQuestion.next('.quiz-question-item');
            scrollToTarget($nextQuestion);
        }
    });

    /**
     * ОБРАБОТЧИК КНОПКИ "ПОЛУЧИТЬ РЕЗУЛЬТАТ"
     */
    $quizForm.on('submit', function (e) {
        e.preventDefault();
        highlightRequiredErrors();

        // 1. ПРОВЕРЯЕМ, есть ли хотя бы один неотвеченный ОБЯЗАТЕЛЬНЫЙ вопрос
        const hasUnansweredRequired = findFirstUnanswered(true);

        // Если да, то включаем режим исправления
        if (hasUnansweredRequired) {
            validationModeActive = true; // Активируем режим

            // 2. ИЩЕМ самый первый неотвеченный вопрос ЛЮБОГО ТИПА
            const $firstOverallUnanswered = findFirstUnanswered(false);

            // 3. Скроллим к нему
            scrollToTarget($firstOverallUnanswered);
            return; // ОСТАНАВЛИВАЕМ ВЫПОЛНЕНИЕ
        }

        // Если обязательных неотвеченных вопросов нет, СРАЗУ переходим к отправке AJAX.
        $getResultsButton.prop('disabled', true).text(myQuizRunnerAjax.messages.getting_results || 'Получение результатов...');

        var selectedAnswers = {};
        $quizForm.find('input[type="radio"]:checked').each(function () {
            var name = $(this).attr('name');
            var value = $(this).val();
            selectedAnswers[name] = value;
        });

        $.ajax({
            url: myQuizRunnerAjax.ajax_url, type: 'POST', data: {
                action: 'my_quiz_builder_get_results',
                nonce: myQuizRunnerAjax.nonce,
                quiz_id: myQuizRunnerAjax.quiz_id,
                answers: selectedAnswers
            }, success: function (response) {
                const $resultsDisplayWrapper = $('#quiz-results-display');
                if (response.success) {
                    displayResults(response.data.results);
                    $resultsDisplayWrapper.show();
                    scrollToTarget($resultsDisplayWrapper);
                } else {
                    $('#quiz-results-display .results-content').html(`<div class="quiz-error-message">${response.data.message || myQuizRunnerAjax.messages.error_getting_results}</div>`);
                    $resultsDisplayWrapper.show();
                    scrollToTarget($resultsDisplayWrapper);
                }
            }, error: function () {
                const $resultsDisplayWrapper = $('#quiz-results-display');
                $('#quiz-results-display .results-content').html(`<div class="quiz-error-message">${myQuizRunnerAjax.messages.error_getting_results}</div>`);
                $resultsDisplayWrapper.show();
            }, complete: function () {
                $getResultsButton.prop('disabled', false).text(myQuizRunnerAjax.messages.get_result_button || 'Получить результат');
            }
        });
    });

    function displayResults(results) {
        const $resultsContent = $('#quiz-results-display .results-content');
        let html = '';
        if (results && results.length > 0) {
            results.forEach(result => {
                html += `
                    <div class="quiz-single-result">
                        <p><strong>${result.modality_name}:</strong> <span class="result-score">${result.score} ${myQuizRunnerAjax.messages.score_label || 'баллов'}</span></p>
                        <div class="result-description">${result.description}</div>
                    </div>
                `;
            });
        } else {
            html = `<p>${myQuizRunnerAjax.messages.no_results_found || 'Не удалось определить результаты.'}</p>`;
        }
        $resultsContent.html(html);
    }

    $('#print-quiz-results').on('click', function () {
        $('body').addClass('print-results-only');

        // Добавляем небольшую задержку перед печатью для совместимости с Android
        setTimeout(function () {
            window.print();
        }, 100);
    });

    $('#print-full-quiz').on('click', function () {
        $('body').addClass('print-full-quiz');

        // Добавляем такую же задержку и здесь
        setTimeout(function () {
            window.print();
        }, 100);
    });

    $(window).on('afterprint', function () {
        $('body').removeClass('print-results-only print-full-quiz');
    });
});