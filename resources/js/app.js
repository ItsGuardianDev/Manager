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
        const withError = root.querySelector('[data-tab-panel] .text-rose-600');
        const first = root.querySelector('[data-tab-target]')?.dataset.tabTarget;
        selectTab(root, withError?.closest('[data-tab-panel]').dataset.tabPanel ?? first);
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
