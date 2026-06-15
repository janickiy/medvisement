document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.js-med-cr-search');
    if (!form) {
        return;
    }

    const input = form.querySelector('.js-med-cr-input');
    const specialty = form.querySelector('.js-med-cr-specialty');
    const specialtyTermId = form.querySelector('.js-med-cr-specialty-term-id');

    if (!input || !specialty || !specialtyTermId) {
        return;
    }

    const syncSpecialty = function () {
        const selectedOption = specialty.options[specialty.selectedIndex];
        const termId = selectedOption ? (selectedOption.dataset.termId || '') : '';

        specialtyTermId.value = termId;

        if (termId) {
            specialtyTermId.setAttribute('name', 'specialties[]');
        } else {
            specialtyTermId.removeAttribute('name');
        }
    };

    syncSpecialty();

    specialty.addEventListener('change', function () {
        syncSpecialty();

        if (typeof window.epasAPI !== 'undefined' && typeof window.epasAPI.hideAutosuggestBox === 'function') {
            window.epasAPI.hideAutosuggestBox();
        }

        if (input.value.trim().length >= 2) {
            input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
        }
    });

    if (
        typeof window.wp === 'undefined'
        || typeof window.wp.hooks === 'undefined'
        || typeof window.wp.hooks.addFilter !== 'function'
        || !document.body.classList.contains('medvise-clinical-recommendations-archive')
    ) {
        return;
    }

    const addFilter = window.wp.hooks.addFilter;

    addFilter('ep.Autosuggest.element', 'medvisement/clinical-recommendations-autosuggest-element', function (element) {
        element.classList.add('med-cr-autosuggest');
        return element;
    });

    addFilter('ep.Autosuggest.data', 'medvisement/clinical-recommendations-autosuggest-data', function (data) {
        if (!data || !data.hits || !Array.isArray(data.hits.hits)) {
            return data;
        }

        data.hits.hits = data.hits.hits.map(function (hit) {
            if (!hit || !hit._source || !hit._source.permalink) {
                return hit;
            }

            try {
                const url = new URL(hit._source.permalink, window.location.origin);
                const segments = url.pathname.split('/').filter(Boolean);
                const slug = segments.length ? segments[segments.length - 1] : '';

                if (slug) {
                    hit._source.permalink = MedviseClinicalRecommendations.archiveUrl.replace(/\/$/, '') + '/' + slug + '/';
                }
            } catch (error) {
                return hit;
            }

            return hit;
        });

        return data;
    });
});
