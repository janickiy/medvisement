document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.clinical-guidelines-search');
    if (!form) {
        return;
    }

    const specialtySelect = form.querySelector('.clinical-guidelines-search__select');
    const searchInput = form.querySelector('.clinical-guidelines-search__input');
    const hiddenSpecialtiesContainer = form.querySelector('.clinical-guidelines-search__specialties-hidden');
    const sidebarLinks = document.querySelectorAll('.clinical-guidelines-sidebar__link');

    const syncHiddenSpecialties = function () {
        if (!specialtySelect || !hiddenSpecialtiesContainer) {
            return;
        }

        hiddenSpecialtiesContainer.innerHTML = '';

        const selectedOption = specialtySelect.options[specialtySelect.selectedIndex];
        const termId = selectedOption ? selectedOption.dataset.termId : '';

        if (!termId) {
            return;
        }

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'specialties[]';
        hiddenInput.value = termId;
        hiddenSpecialtiesContainer.appendChild(hiddenInput);
    };

    const syncSidebarState = function () {
        if (!specialtySelect || !sidebarLinks.length) {
            return;
        }

        const selectedSlug = specialtySelect.value;

        sidebarLinks.forEach(function (link) {
            link.classList.toggle('is-active', link.dataset.specialtySlug === selectedSlug);

            if (!selectedSlug && link.dataset.specialtySlug === '') {
                link.classList.add('is-active');
            }
        });
    };

    const syncAutosuggestResultsLink = function () {
        const autosuggestLink = form.querySelector('.to-all-results a');
        if (!autosuggestLink) {
            return;
        }

        const params = new URLSearchParams();
        if (searchInput && searchInput.value.trim() !== '') {
            params.set('cr_query', searchInput.value.trim());
        }
        if (specialtySelect && specialtySelect.value) {
            params.set('cr_specialty', specialtySelect.value);
        }

        autosuggestLink.href = params.toString() ? form.action + '?' + params.toString() : form.action;
    };

    if (specialtySelect) {
        specialtySelect.addEventListener('change', function () {
            syncHiddenSpecialties();
            syncSidebarState();
            syncAutosuggestResultsLink();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', syncAutosuggestResultsLink);
    }

    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (!specialtySelect) {
                return;
            }

            specialtySelect.value = link.dataset.specialtySlug || '';
            syncHiddenSpecialties();
            syncSidebarState();
        });
    });

    const observer = new MutationObserver(syncAutosuggestResultsLink);
    observer.observe(form, {
        childList: true,
        subtree: true,
    });

    syncHiddenSpecialties();
    syncSidebarState();
    syncAutosuggestResultsLink();
});
