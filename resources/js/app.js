document.addEventListener('change', (event) => {
    const form = event.target.closest('form[data-autosubmit]');

    if (form && !event.target.matches('input[type="search"]')) {
        form.requestSubmit();
    }
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle]');

    if (toggle) {
        document.getElementById(toggle.dataset.toggle)?.classList.toggle('hidden');
    }

    const tab = event.target.closest('[data-tab-target]');

    if (tab) {
        selectTab(tab.closest('[data-tabs]'), tab.dataset.tabTarget);
    }
});

function selectTab(root, name) {
    root.querySelectorAll('[data-tab-target]').forEach((button) => {
        const active = button.dataset.tabTarget === name;
        button.classList.toggle('border-brand-600', active);
        button.classList.toggle('text-brand-700', active);
        button.classList.toggle('border-transparent', !active);
        button.classList.toggle('text-slate-500', !active);
    });
    root.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== name;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
        const withError = root.querySelector('[data-tab-panel] p.text-rose-600');
        const first = root.querySelector('[data-tab-target]')?.dataset.tabTarget;
        const requested = root.dataset.initial;
        const exists = requested && root.querySelector(`[data-tab-target="${requested}"]`);
        selectTab(root, withError?.closest('[data-tab-panel]').dataset.tabPanel ?? (exists ? requested : first));
    });

    const toast = document.getElementById('toast');
    if (toast) {
        setTimeout(() => toast.remove(), 4000);
    }
});

function syncReveal(checkbox) {
    const target = document.getElementById(checkbox.dataset.reveal);
    if (target) {
        target.hidden = !checkbox.checked;
    }
}

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-reveal]')) {
        syncReveal(event.target);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-reveal]').forEach(syncReveal);
});

document.addEventListener('input', (event) => {
    const timeline = event.target.closest('[data-timeline]');
    const docs = event.target.closest('[data-documents]');

    if (timeline && event.target.matches('[data-timeline-q]')) {
        filterTimeline(timeline);
    }

    if (docs && event.target.matches('[data-doc-q]')) {
        filterDocuments(docs);
    }
});

document.addEventListener('change', (event) => {
    const timeline = event.target.closest('[data-timeline]');
    const docs = event.target.closest('[data-documents]');

    if (timeline && event.target.matches('[data-timeline-type]')) {
        filterTimeline(timeline);
    }

    if (docs && event.target.matches('[data-doc-ext]')) {
        filterDocuments(docs);
    }

    if (event.target.matches('[data-file-input]')) {
        const label = event.target.closest('label').querySelector('[data-file-label]');
        label.textContent = event.target.files[0]?.name ?? 'Voeg bijlage toe';
    }
});

function filterTimeline(root) {
    const query = root.querySelector('[data-timeline-q]').value.trim().toLowerCase();
    const type = root.querySelector('[data-timeline-type]').value;
    let visible = 0;

    root.querySelectorAll('[data-timeline-item]').forEach((item) => {
        const show = (!type || item.dataset.type === type) && (!query || item.dataset.text.includes(query));
        item.classList.toggle('hidden', !show);
        visible += show ? 1 : 0;
    });

    root.querySelector('[data-timeline-empty]')?.classList.toggle('hidden', visible > 0);
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-dialog]');

    if (opener) {
        document.getElementById(opener.dataset.openDialog)?.showModal();
    }

    if (event.target.closest('[data-close-dialog]')) {
        event.target.closest('dialog')?.close();
    }
});

document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-add-row]');

    if (!add) {
        return;
    }

    const body = add.closest('[data-rows]').querySelector('[data-rows-body]');
    const last = body.lastElementChild;
    const index = body.children.length;
    const row = last.cloneNode(true);

    row.querySelectorAll('input, select').forEach((field) => {
        field.name = field.name.replace(/\[\d+\]/, `[${index}]`);
        field.value = '';
    });

    body.appendChild(row);
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-method-select]')) {
        const show = ['fixed_price', 'subscription'].includes(event.target.value);
        document.querySelector('[data-price-field]')?.classList.toggle('hidden', !show);
    }

    if (event.target.matches('[data-default-service]')) {
        const option = event.target.selectedOptions[0];
        const name = document.getElementById('name');
        const method = document.getElementById('invoice_method');

        if (option?.dataset.name && name && !name.value) {
            name.value = option.dataset.name;
        }

        if (option?.dataset.method && method) {
            method.value = option.dataset.method;
            method.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
});

function filterDocuments(root) {
    const query = root.querySelector('[data-doc-q]').value.trim().toLowerCase();
    const ext = root.querySelector('[data-doc-ext]').value;

    root.querySelectorAll('[data-doc-row]').forEach((row) => {
        row.classList.toggle('hidden', !((!ext || row.dataset.ext === ext) && (!query || row.dataset.text.includes(query))));
    });
}
