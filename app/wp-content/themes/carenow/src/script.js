import {addFilter} from '@wordpress/hooks'

/**
 * Filter the HTML for an Autosuggest suggestion.
 *
 * @filter ep.Autosuggest.itemHTML
 * @since 4.3.1
 *
 * @param {string} itemHTML Item HTML.
 * @param {object} option Elasticsearch record for suggestion.
 * @param {number} index Suggestion index.
 * @param {string} searchText Search term.
 * @returns {string} Item HTML.
 */
function ep_hightlight_as_item(itemHTML, option, index, searchText) {

    //Бесплатная или платная статья
    var free_article = false;
    if ( (typeof option._source?.meta?.ep_free?.[0]?.value !== "undefined" && option._source.meta.ep_free[0].value == 1) ||
        window.med_user.open_articles.includes(option._source.post_id) ||
        (
            // Проверяем ид термов специальностей, сравниваем с открытыми специальностями
            typeof option._source?.terms?.specialty === "object" &&
            window.med_user.open_specialties.some(
                r => option._source.terms.specialty.map(function (el) { return el.term_id; }).includes(r)
            )
        )
        || option._source.post_type === 'substance'
        || option._source.post_type === 'custom_quiz'
    )
    {
        free_article = true;
    }

    let searhTextEscaped = searchText.replace(/\\([\s\S])|(")/g, '&quot;');
    let resultsTextEscaped = option._source.post_title.replace(/\\([\s\S])|(")/g, '&quot;');

    itemHTML = `<li class="autosuggest-item" role="option" aria-selected="false" id="autosuggest-option-${index}">
                    <a href="${option._source.permalink}" class="autosuggest-link"
                    data-search="${searhTextEscaped}" data-url="${option._source.permalink}" tabindex="-1">
                        <div class="autosuggest-item_title">`;

    if ( free_article ) {
        itemHTML += '<i class="fa-solid fa-lock-open"></i>';
    }
    else {
        itemHTML += '<i class="fa-solid fa-lock"></i>';
    }

    itemHTML += `${resultsTextEscaped}</div>`

    // Если найдено совпадение по тексту статьи
    if ( typeof option.highlight?.post_content !== "undefined" ) {
        itemHTML += `<div class="autosuggest-item_text">`;

        // Удаляем дубликаты (иногда это в предисловии и потом в тексте)
        option.highlight.post_content = [...new Set(option.highlight.post_content)];

        for (let i = 0; i < option.highlight.post_content.length; i++) {
            // Не больше трех вырезок
            if ( i === 3 ) {
                break;
            }
            option.highlight.post_content[i] = option.highlight.post_content[i].replace(/\n+/g, " ");
            option.highlight.post_content[i] = option.highlight.post_content[i].replace(/\s\s+/g, ' ');
            itemHTML += option.highlight.post_content[i];
            itemHTML += "...<br>";
            itemHTML = itemHTML.replace(/\.\.\.+/, '...');
        }

        itemHTML += `</div>`;
    }

    itemHTML += `</a>
            </li>`

    return itemHTML;
}
addFilter('ep.Autosuggest.itemHTML', 'medvisement/ep-Autosuggest', ep_hightlight_as_item);

/**
 * Filter the HTML for the list of Autosuggest suggestions.
 *
 * @filter ep.Autosuggest.listHTML
 * @since 4.3.1
 *
 * @param {string} listHTML List HTML.
 * @param {object[]} options Elasticsearch records for suggestions.
 * @param {Element} input Input element that triggered Autosuggest.
 * @returns {string} List HTML.
 */
function ep_as_list(listHTML, options, input) {

    const regExp = new RegExp('</li>', "gi");
    let items_count = (listHTML.match(regExp) || []).length;

    if (items_count > 10) {
        let post_type_params = jQuery(input).closest('form').find('input[name="post_type[]"]:checked');
        let specialties_params = jQuery(input).closest('form').find('input[name="specialties[]"]');

        let url = '/?s=' + input.value;

        if ( post_type_params.length > 0 ) {
            url += '&' + post_type_params.serialize();
        }
        if ( specialties_params.length > 0 ) {
            url += '&' + specialties_params.serialize();
        }

        var toResultHTML =
            '<li class="autosuggest-item to-all-results">' +
            '<a href="' + url + '" class="autosuggest-link" data-search="" data-url="' + url + '" tabindex="-1">' +
            'Показать все результаты' +
            '</a>' +
            '</li>';

        listHTML += toResultHTML;
    }

    return listHTML;
}
addFilter('ep.Autosuggest.listHTML', 'medvisement/ep-Autosuggest', ep_as_list);

/**
 * Filter the Elasticsearch query used for Autosuggest.
 *
 * @filter ep.Autosuggest.query
 * @since 4.3.1
 *
 * @param {object} query Elasticsearch query.
 * @param {string} searchText Search term.
 * @param {Element} input Input element that triggered Autosuggest.
 * @param searchTextLemmatized
 * @returns {object} Elasticsearch query.
 */
function ep_as_query(query, searchText, input, searchTextLemmatized) {

    // Игнорируем query сгенерированный в epas.query. Бэк и фронт разделены
    const epBaseQuery = require('./ep_base_query');

    query = JSON.parse(epBaseQuery(searchText, searchTextLemmatized));

    // Типы постов
    const section_name = jQuery(input).closest('form').find('input[name="section_name[]"]:checked');
    let section_names = [];
    for (const section_name_input of section_name) {
        let value;
        if ( section_name_input.value.indexOf('_') !== -1 && section_name_input.value !== 'custom_quiz' ) {
            value = section_name_input.value.substring(section_name_input.value.indexOf('_'), 0);
        }
        else {
            value = section_name_input.value;
        }
        section_names.push(value);
    }

    // Убираем тип поста из post_filter
    if (section_names.length) {
        section_names = [...new Set(section_names)];
        query.post_filter.bool.must[0].terms['post_type.raw'] = section_names;
    }

    // Убираем условия убранных постов из query
    if (section_names.length) {
        query.query.bool.should =
            query.query.bool.should.filter(item => section_names.includes(item.bool.filter[0].match['post_type.raw']));
    }

    // Заболевания - смотрим, есть ли фильтр разделов
    let disease_types = [];
    for (const section_name_input of section_name) {
        let value;
        if (section_name_input.value.indexOf('_') !== -1) {
            value = section_name_input.value.substring((section_name_input.value.indexOf('_') + 1));
        }
        if ( value ) {
            disease_types.push(value);
        }
    }

    // Заболевания - убираем не нужные разделы
    if (disease_types.length) {
        disease_types = [...new Set(disease_types)];

        query.query.bool.should.forEach(function (item, index) {
            if ( item.bool.filter[0].match['post_type.raw'] === 'disease' ) {
                query.query.bool.should[index].bool.must.push({
                    'terms': {
                        'terms.article-type.slug': disease_types
                    }
                });
            }
        });

        query.post_filter.bool.must.push();
    }

    // Какие поля возвращаются из поиска, post_content сильно замедляет
    query._source = {
        "includes": [ "post_id", "post_title", "permalink", "meta", "terms", "post_type" ]
    };

    // По каким специальностям ищем (включаем, если ищем по специальностям)
    if (section_names.includes('disease')) {
        var specialties = jQuery(input).closest('form').find('input[name="specialties[]"]');
        var specialties_ids = [];
        for (const specialty_input of specialties) {
            specialties_ids.push(specialty_input.value);
        }
        if (specialties_ids.length) {
            query.post_filter.bool.must.push(
                {
                    'terms': {
                        'terms.specialty.term_id': specialties_ids
                    }
                }
            );
        }
    }

    console.log(query);

    return query;
}
addFilter('ep.Autosuggest.query', 'medvisement/ep-Autosuggest', ep_as_query);

/**
 * Filter the Elasticsearch response data used for Autosuggest.
 *
 * @filter ep.Autosuggest.data
 * @since 4.3.1
 *
 * @param {object} data Response data.
 * @param {string} searchTerm Search term.
 * @returns {object} Response data.
 */
function ep_autosuggest_data(data, searchTerm) {

    var hits = data.hits.hits;

    //Перебираем документы
    jQuery.each(hits, function( doc_index, doc_value ) {

        let inner_hits_name = doc_value._source.post_type + '_titles_hits';

        if ( doc_value.inner_hits[inner_hits_name].hits.hits.length === 0 )
            return;

        hits[doc_index]._source.post_title = doc_value.inner_hits[inner_hits_name].hits.hits[0]._source.title;
    });

    data.hits.hits = hits;

    return data;
}
addFilter('ep.Autosuggest.data', 'medvisement/ep-Autosuggest', ep_autosuggest_data);

document.addEventListener("DOMContentLoaded", function(event) {
    const search_settings_button = document.querySelector('.search-form__settings');

    if ( search_settings_button === null ) {
        return true;
    }

    const container = search_settings_button.closest('.search-form').querySelector('.search-form-filters');

    if (container === null) {
        return true;
    }

    search_settings_button.addEventListener('click', () => {

        let current_settings = Cookies.get('es_search_settings');

        if (current_settings === undefined) {
            current_settings = {
                'visible': true,
                'section_name': [
                    'disease_article',
                    'disease_clinical-guidelines',
                    'substance'
                ]
            };
        }
        else {
            current_settings = JSON.parse(current_settings);
        }

        if ( jQuery(container).is(':visible')) {
            jQuery(container).slideUp();
            current_settings.visible = false;
            Cookies.set(`es_search_settings`, current_settings, { expires: 365 });
        } else {
            jQuery(container).slideDown();
            current_settings.visible = true;
            Cookies.set(`es_search_settings`, current_settings, { expires: 365 });
        }

    });

    // Изменение разделов поиска
    const section_name_checkboxes = document.querySelectorAll('input[name="section_name[]"]');

    if (section_name_checkboxes.length !== 0) {
        section_name_checkboxes.forEach(section_name_checkbox => {

            section_name_checkbox.addEventListener('change', (event) => {

                let current_settings = JSON.parse(Cookies.get('es_search_settings'));
                let in_settings = current_settings.section_name.indexOf(event.currentTarget.value);

                if (current_settings === undefined) {
                    current_settings = {
                        'visible': true,
                        'section_name': [
                            'disease_article',
                            'disease_clinical-guidelines',
                            'substance'
                        ]
                    };
                }
                else {
                    current_settings = JSON.parse(current_settings);
                }

                if (event.currentTarget.checked) {
                    // Добавляем элемент
                    if (in_settings === -1) {
                        current_settings.section_name.push(event.currentTarget.value);
                    }
                } else {
                    // Убираем элемент
                    if (in_settings !== -1) {
                        current_settings.section_name.splice(in_settings, 1);
                    }
                }

                Cookies.set(`es_search_settings`, current_settings, {expires: 365});
            });

        });
    }
});