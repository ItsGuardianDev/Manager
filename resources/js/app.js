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

    if (opener && window.agendaDragged) {
        window.agendaDragged = false;

        return;
    }

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

/* Agenda: drag to create, move and resize hour blocks (15 minute grid). */
(() => {
    const SNAP = 15;
    const agenda = document.querySelector('[data-agenda]');

    if (!agenda) {
        return;
    }

    const startHour = parseInt(agenda.dataset.startHour, 10);
    const endHour = parseInt(agenda.dataset.endHour, 10);
    const columns = [...agenda.querySelectorAll('[data-agenda-col]')];
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    const minuteAt = (column, clientY) => {
        const box = column.getBoundingClientRect();
        const raw = startHour * 60 + ((clientY - box.top) / box.height) * (endHour - startHour) * 60;

        return clamp(Math.round(raw / SNAP) * SNAP, startHour * 60, endHour * 60);
    };

    const columnAt = (clientX) => columns.find((column) => {
        const box = column.getBoundingClientRect();

        return clientX >= box.left && clientX < box.right;
    });

    const place = (element, column, from, to) => {
        const total = (endHour - startHour) * 60;
        element.style.top = `${((from - startHour * 60) / total) * 100}%`;
        element.style.height = `${((to - from) / total) * 100}%`;

        if (element.parentElement !== column) {
            column.appendChild(element);
        }
    };

    const submitUpdate = (data, values) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = data.urls.update;
        const token = document.querySelector('input[name="_token"]')?.value;
        const fields = { _token: token, _method: 'PUT', employee_id: data.employee_id, project_id: data.project_id, project_service_id: data.project_service_id, hour_type_id: data.hour_type_id ?? '', note: data.note ?? '', ...values };

        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };

    agenda.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
            return;
        }

        const block = event.target.closest('[data-entry-open]');
        const column = event.target.closest('[data-agenda-col]');

        if (block) {
            if (block.hasAttribute('data-movable')) {
                dragBlock(event, block, event.target.closest('[data-resize]') !== null);
            }

            return;
        }

        if (column) {
            dragCreate(event, column);
        }
    });

    function dragCreate(event, column) {
        const anchor = minuteAt(column, event.clientY);
        const ghost = document.createElement('div');
        ghost.className = 'pointer-events-none absolute inset-x-1 rounded-lg bg-brand-500/30 ring-2 ring-brand-500';
        let current = anchor + SNAP;
        let moved = false;

        place(ghost, column, anchor, current);
        column.appendChild(ghost);

        const move = (e) => {
            moved = true;
            current = minuteAt(column, e.clientY);

            if (current === anchor) {
                current = anchor + SNAP;
            }

            place(ghost, column, Math.min(anchor, current), Math.max(anchor, current));
        };

        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            ghost.remove();

            const from = Math.min(anchor, current);
            const to = Math.max(anchor, current);
            const dialog = document.getElementById('entry-dialog');

            if (!dialog) {
                return;
            }

            fillEntryDialog(dialog, { date: column.dataset.agendaCol, start_time: fromMinutes(from), end_time: fromMinutes(moved ? to : from + 60), hours: ((moved ? to - from : 60) / 60).toFixed(2) });
            dialog.showModal();
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    function dragBlock(event, block, resizing) {
        const data = JSON.parse(block.dataset.entry);
        const origin = block.parentElement;
        const startY = event.clientY;
        const from = toMinutes(data.start_time);
        const to = data.end_time ? toMinutes(data.end_time) : from + Math.round(data.hours * 60);
        let target = { column: origin, from, to };
        let dragging = false;

        const move = (e) => {
            if (!dragging && Math.abs(e.clientY - startY) < 4 && e.clientX === event.clientX) {
                return;
            }

            if (!dragging) {
                dragging = true;
                window.agendaDragged = true;
                block.classList.add('z-30', 'opacity-80', 'shadow-lg');
            }

            if (resizing) {
                const end = Math.max(from + SNAP, minuteAt(origin, e.clientY));
                target = { column: origin, from, to: end };
            } else {
                const column = columnAt(e.clientX) ?? target.column;
                const delta = Math.round(((e.clientY - startY) / origin.getBoundingClientRect().height) * (endHour - startHour) * 60 / SNAP) * SNAP;
                const start = clamp(from + delta, startHour * 60, endHour * 60 - (to - from));
                target = { column, from: start, to: start + (to - from) };
            }

            place(block, target.column, target.from, target.to);
        };

        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);

            if (!dragging) {
                return;
            }

            setTimeout(() => { window.agendaDragged = false; }, 0);

            if (target.column === origin && target.from === from && target.to === to) {
                return;
            }

            submitUpdate(data, resizing
                ? { date: data.date, start_time: fromMinutes(target.from), end_time: fromMinutes(target.to) }
                : { date: target.column.dataset.agendaCol, start_time: fromMinutes(target.from), end_time: fromMinutes(target.to), hours: data.hours });
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }
})();
