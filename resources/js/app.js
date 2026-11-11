document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)
        || form.method.toUpperCase() === 'GET'
        || form.matches('[data-fingerprint-login]')) {
        return;
    }

    if (form.dataset.submissionInProgress === 'true') {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    form.dataset.submissionInProgress = 'true';
    form.setAttribute('aria-busy', 'true');

    form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
        button.setAttribute('aria-disabled', 'true');
        button.classList.add('pointer-events-none', 'cursor-wait', 'opacity-60');
    });
}, true);

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    document.querySelectorAll('form[data-submission-in-progress="true"]').forEach((form) => {
        delete form.dataset.submissionInProgress;
        form.removeAttribute('aria-busy');

        form.querySelectorAll('[aria-disabled="true"]').forEach((button) => {
            button.removeAttribute('aria-disabled');
            button.classList.remove('pointer-events-none', 'cursor-wait', 'opacity-60');
        });
    });
});
