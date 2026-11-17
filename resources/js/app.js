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
        document.querySelector('[data-cycle-field]')?.classList.toggle('hidden', event.target.value !== 'subscription');
    }

    if (event.target.matches('[data-default-service]')) {
        const url = new URL(window.location.href);

        if (event.target.value) {
            url.searchParams.set('default_service', event.target.value);
        } else {
            url.searchParams.delete('default_service');
        }

        window.location.href = url.toString();
    }
});

function filterDocuments(root) {
    const query = root.querySelector('[data-doc-q]').value.trim().toLowerCase();
    const ext = root.querySelector('[data-doc-ext]').value;

    root.querySelectorAll('[data-doc-row]').forEach((row) => {
        row.classList.toggle('hidden', !((!ext || row.dataset.ext === ext) && (!query || row.dataset.text.includes(query))));
    });
}

/* Hours: entry dialog, service filtering, time maths, timesheet. */
const formatNumber = (value) => (Math.round(value * 100) / 100).toString().replace('.', ',');

function syncServiceOptions(scope, keep = true) {
    const project = scope?.querySelector('[data-project-select]');
    const service = scope?.querySelector('[data-service-select]');

    if (!project || !service) {
        return;
    }

    let valid = false;

    service.querySelectorAll('option[data-project]').forEach((option) => {
        const match = option.dataset.project === project.value;
        option.hidden = !match;
        option.disabled = !match;
        valid = valid || (match && option.selected);
    });

    if (!valid || !keep) {
        service.value = '';
    }

    syncBudgetHint(scope);
}

function syncBudgetHint(scope) {
    const hint = scope?.querySelector('[data-budget-hint]');
    const option = scope?.querySelector('[data-service-select]')?.selectedOptions[0];

    if (!hint) {
        return;
    }

    if (!option || !option.dataset.budget) {
        hint.textContent = '';

        return;
    }

    const budget = parseFloat(option.dataset.budget);
    const spent = parseFloat(option.dataset.spent || '0');
    const remaining = budget - spent;

    hint.textContent = budget > 0
        ? `Budget ${formatNumber(budget)} u · besteed ${formatNumber(spent)} u · restant ${formatNumber(remaining)} u`
        : `Geen urenbudget · besteed ${formatNumber(spent)} u`;
    hint.classList.toggle('text-rose-600', budget > 0 && remaining < 0);
    hint.classList.toggle('text-slate-500', !(budget > 0 && remaining < 0));
}

function toMinutes(value) {
    const [hours, minutes] = value.split(':').map(Number);

    return hours * 60 + minutes;
}

function fromMinutes(total) {
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function fillEntryDialog(dialog, data) {
    const form = dialog.querySelector('[data-entry-form]');
    const editing = Boolean(data.id);
    const set = (name, value) => {
        const field = form.elements[name];

        if (field) {
            field.value = value ?? '';
        }
    };

    form.reset();
    form.action = editing ? data.urls.update : form.dataset.storeUrl;
    form.querySelector('[data-method-field]').disabled = !editing;
    dialog.querySelector('[data-dialog-title]').textContent = editing ? 'Registratie' : 'Uren registreren';

    set('date', data.date);
    set('project_id', data.project_id ?? '');
    syncServiceOptions(form, false);
    set('project_service_id', data.project_service_id ?? '');
    syncBudgetHint(form);
    set('hour_type_id', data.hour_type_id ?? '');
    set('start_time', data.start_time);
    set('end_time', data.end_time);
    set('hours', data.hours);
    set('note', data.note);

    if (data.employee_id && form.elements.employee_id) {
        form.elements.employee_id.value = data.employee_id;
    }

    const readonly = editing && !data.editable;
    form.querySelectorAll('input:not([type=hidden]), select').forEach((field) => { field.disabled = readonly; });
    form.querySelector('[data-save-button]').classList.toggle('hidden', readonly);
    dialog.querySelectorAll('[data-locked-only]').forEach((element) => element.classList.toggle('hidden', !(data.locked && data.editable)));
    dialog.querySelector('[data-edit-only]').classList.toggle('hidden', !(editing && data.editable));

    const status = dialog.querySelector('[data-entry-status]');
    status.classList.toggle('hidden', !editing);
    status.textContent = editing ? `Status: ${data.status}${data.reject_reason ? ` · reden afkeuring: ${data.reject_reason}` : ''}${data.locked ? ' · verwerkt voor facturatie' : ''}` : '';

    if (editing && data.editable) {
        dialog.querySelector('[data-action-form="duplicate"]').action = data.urls.duplicate;
        dialog.querySelector('[data-action-form="destroy"]').action = data.urls.destroy;

        const split = dialog.querySelector('[data-action-form="split"]');
        split.action = data.urls.split;
        split.querySelector('[data-project-select]').value = data.project_id;
        syncServiceOptions(split, false);
        split.querySelector('[data-service-select]').value = data.project_service_id;
    }
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-entry-open]');

    if (opener) {
        const dialog = document.getElementById('entry-dialog');
        fillEntryDialog(dialog, opener.dataset.entry ? JSON.parse(opener.dataset.entry) : { date: opener.dataset.date });
        dialog.showModal();
    }

    const all = event.target.closest('[data-check-all]');

    if (all) {
        all.closest('form').querySelectorAll('[data-row-check]').forEach((box) => { box.checked = all.checked; });
    }

    const add = event.target.closest('[data-week-add]');

    if (add) {
        const table = add.closest('[data-timesheet]');
        const body = table.querySelector('[data-week-body]');
        const index = body.querySelectorAll('tr[data-week-row]').length + 1000 + body.children.length;
        const row = table.querySelector('template[data-week-template]').content.firstElementChild.cloneNode(true);

        row.innerHTML = row.innerHTML.replaceAll('__i__', index);
        body.appendChild(row);
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-project-select]')) {
        syncServiceOptions(event.target.closest('[data-service-scope]'), false);
    }

    if (event.target.matches('[data-service-select]')) {
        syncBudgetHint(event.target.closest('[data-service-scope]'));
    }

    const scope = event.target.closest('[data-time-scope]');

    if (scope && event.target.matches('[data-start], [data-end], [data-hours]')) {
        const start = scope.querySelector('[data-start]').value;
        const end = scope.querySelector('[data-end]');
        const hours = scope.querySelector('[data-hours]');

        if (start && end.value && !event.target.matches('[data-hours]')) {
            const minutes = toMinutes(end.value) - toMinutes(start);

            if (minutes > 0) {
                hours.value = (minutes / 60).toFixed(2);
            }
        } else if (start && hours.value && event.target.matches('[data-hours]')) {
            const total = toMinutes(start) + Math.round(parseFloat(hours.value) * 60);

            if (total < 1440) {
                end.value = fromMinutes(total);
            }
        }
    }
});

document.addEventListener('input', (event) => {
    const sheet = event.target.closest('[data-timesheet]');

    if (!sheet || !event.target.matches('[data-cell]')) {
        return;
    }

    const days = {};
    let week = 0;

    sheet.querySelectorAll('[data-cell]').forEach((cell) => {
        const value = parseFloat(cell.value || cell.dataset.value || '0') || 0;
        days[cell.dataset.cell] = (days[cell.dataset.cell] || 0) + value;
        week += value;
    });

    sheet.querySelectorAll('[data-day-total]').forEach((total) => { total.textContent = formatNumber(days[total.dataset.dayTotal] || 0); });
    sheet.querySelectorAll('[data-day-diff]').forEach((diff) => {
        const value = (days[diff.dataset.dayDiff] || 0) - parseFloat(diff.dataset.scheduled);
        diff.textContent = value === 0 ? '0' : `${value > 0 ? '+' : ''}${formatNumber(value)}`;
        diff.classList.toggle('text-rose-600', value < 0);
        diff.classList.toggle('text-emerald-600', value > 0);
    });
    sheet.querySelector('[data-week-total]').textContent = formatNumber(week);
});
