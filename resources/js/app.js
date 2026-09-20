import QRCode from 'qrcode';

const menu = document.querySelector('[data-pn-menu]');
const openMenu = document.querySelector('[data-pn-menu-open]');
const closeMenu = document.querySelector('[data-pn-menu-close]');
let menuTrigger = null;

openMenu?.addEventListener('click', () => {
    menuTrigger = document.activeElement;
    menu?.showModal();
    menu?.querySelector('button, a, [href]')?.focus();
});
closeMenu?.addEventListener('click', () => menu?.close());
menu?.addEventListener('click', (event) => {
    if (event.target === menu) {
        menu.close();
    }
});
menu?.addEventListener('close', () => menuTrigger?.focus());

document.querySelectorAll('[data-pn-password-toggle]').forEach((toggle) => {
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!input) return;
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.textContent = visible ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
    });
});

const shell = document.querySelector('[data-pn-shell]');
const sidebarToggle = document.querySelector('[data-pn-sidebar-toggle]');
if (shell && sidebarToggle) {
    const setSidebarCollapsed = (collapsed) => {
        shell.dataset.pnSidebarCollapsed = collapsed ? 'true' : 'false';
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        const label = collapsed ? sidebarToggle.dataset.collapsedLabel : sidebarToggle.dataset.expandedLabel;
        sidebarToggle.setAttribute('aria-label', label);
        sidebarToggle.setAttribute('title', label);
    };

    let collapsed = false;
    try {
        collapsed = window.localStorage.getItem('playnexus.sidebar.collapsed') === 'true';
    } catch {
        // Storage can be unavailable in restricted browser sessions; expanded remains safe.
    }
    setSidebarCollapsed(collapsed);
    sidebarToggle.addEventListener('click', () => {
        collapsed = shell.dataset.pnSidebarCollapsed !== 'true';
        setSidebarCollapsed(collapsed);
        try {
            window.localStorage.setItem('playnexus.sidebar.collapsed', String(collapsed));
        } catch {
            // The control still works for the current page without persistence.
        }
    });
}

document.querySelectorAll('[data-pn-branch-clock]').forEach((clock) => {
    const timezone = clock.dataset.timezone;
    if (!timezone) return;
    const formatter = new Intl.DateTimeFormat(document.documentElement.lang, {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
    const updateClock = () => {
        const now = new Date();
        const value = formatter.format(now);
        const bidi = clock.querySelector('bdi');
        if (bidi) bidi.textContent = value;
        else clock.textContent = value;
        clock.dateTime = now.toISOString();
    };
    updateClock();
    window.setInterval(updateClock, 30000);
});

const offlineBanner = document.querySelector('[data-pn-offline-banner]');
if (offlineBanner) {
    const setConnectionState = () => {
        const offline = !window.navigator.onLine;
        offlineBanner.classList.toggle('hidden', !offline);
        document.querySelectorAll('form').forEach((form) => {
            const method = (form.getAttribute('method') || 'get').toLowerCase();
            if (method === 'get' || form.action.endsWith('/logout')) return;
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                if (offline && !control.disabled) {
                    control.disabled = true;
                    control.dataset.pnOfflineDisabled = 'true';
                } else if (!offline && control.dataset.pnOfflineDisabled === 'true') {
                    control.disabled = false;
                    delete control.dataset.pnOfflineDisabled;
                }
            });
        });
    };
    window.addEventListener('online', setConnectionState);
    window.addEventListener('offline', setConnectionState);
    offlineBanner.querySelector('[data-pn-offline-retry]')?.addEventListener('click', () => window.location.reload());
    setConnectionState();
}

document.querySelectorAll('[data-pn-locale-select]').forEach((select) => {
    select.addEventListener('change', () => {
        select.form?.requestSubmit();
    });
});

document.querySelectorAll('form[data-pn-form]').forEach((form) => {
    form.addEventListener('input', () => form.dataset.pnDirty = 'true');
    form.addEventListener('change', () => form.dataset.pnDirty = 'true');
    form.addEventListener('submit', (event) => {
        const confirmation = form.dataset.confirm;
        if (confirmation && !window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }

        if (form.dataset.pnSubmitPending === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.pnSubmitPending = 'true';
        form.setAttribute('aria-busy', 'true');
        const submitter = event.submitter || form.querySelector('button[type="submit"]');
        if (submitter) {
            submitter.disabled = true;
            submitter.dataset.originalLabel = submitter.textContent;
            submitter.textContent = submitter.dataset.pnLoadingLabel || submitter.dataset.loadingLabel || submitter.textContent;
        }
    });
});

document.querySelector('[data-pn-focus-on-load]')?.focus();

document.querySelectorAll('[data-pn-settings-tabs]').forEach((tabRoot) => {
    const tabList = tabRoot.querySelector('[role="tablist"]');
    const tabs = tabList ? [...tabList.querySelectorAll('[role="tab"]')] : [];
    const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls'))).filter(Boolean);
    if (!tabList || tabs.length === 0 || panels.length === 0) return;

    const activate = (tab, focus = false) => {
        const panelId = tab.getAttribute('aria-controls');
        tabs.forEach((candidate) => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.tabIndex = selected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.id !== panelId;
        });
        if (focus) tab.focus();
    };

    const fromHash = tabs.find((tab) => tab.getAttribute('aria-controls') === window.location.hash.slice(1));
    activate(fromHash || tabs[0]);

    tabList.addEventListener('click', (event) => {
        const tab = event.target.closest('[role="tab"]');
        if (!tab || !tabs.includes(tab)) return;
        event.preventDefault();
        activate(tab);
        history.replaceState(null, '', `#${tab.getAttribute('aria-controls')}`);
    });

    tabList.addEventListener('keydown', (event) => {
        const currentIndex = tabs.indexOf(document.activeElement);
        if (currentIndex < 0) return;

        const rtl = document.documentElement.dir === 'rtl';
        let nextIndex = currentIndex;
        if (event.key === 'Home') nextIndex = 0;
        if (event.key === 'End') nextIndex = tabs.length - 1;
        if (event.key === 'ArrowRight') nextIndex = (currentIndex + (rtl ? -1 : 1) + tabs.length) % tabs.length;
        if (event.key === 'ArrowLeft') nextIndex = (currentIndex + (rtl ? 1 : -1) + tabs.length) % tabs.length;
        if (nextIndex === currentIndex) return;

        event.preventDefault();
        const nextTab = tabs[nextIndex];
        activate(nextTab, true);
        history.replaceState(null, '', `#${nextTab.getAttribute('aria-controls')}`);
    });

    window.addEventListener('hashchange', () => {
        const tab = tabs.find((candidate) => candidate.getAttribute('aria-controls') === window.location.hash.slice(1));
        if (tab) activate(tab);
    });
});

const hoursFor = (day) => ({
    closed: document.querySelector(`[data-pn-hours-closed="${day}"]`),
    opens: document.querySelector(`[data-pn-hours-opens="${day}"]`),
    closes: document.querySelector(`[data-pn-hours-closes="${day}"]`),
});

const copyHours = (sourceDay, targetDay) => {
    const source = hoursFor(sourceDay);
    const target = hoursFor(targetDay);
    if (!source.closed || !target.closed) return;
    target.closed.value = source.closed.value;
    target.opens.value = source.opens.value;
    target.closes.value = source.closes.value;
    [target.closed, target.opens, target.closes].forEach((field) => field.dispatchEvent(new Event('change', { bubbles: true })));
};

document.querySelectorAll('[data-pn-hours-preset]').forEach((button) => {
    button.addEventListener('click', () => {
        if (button.dataset.pnHoursPreset === 'weekdays') {
            for (let day = 1; day <= 5; day += 1) {
                const fields = hoursFor(day);
                if (!fields.closed) continue;
                fields.closed.value = '0';
                fields.opens.value = '09:00';
                fields.closes.value = '18:00';
            }
            [6, 7].forEach((day) => {
                const fields = hoursFor(day);
                if (fields.closed) {
                    fields.closed.value = '1';
                    fields.opens.value = '';
                    fields.closes.value = '';
                }
            });
        } else if (button.dataset.pnHoursPreset === 'weekend') {
            [6, 7].forEach((day) => {
                const fields = hoursFor(day);
                if (fields.closed) {
                    fields.closed.value = '1';
                    fields.opens.value = '';
                    fields.closes.value = '';
                }
            });
        }
        button.closest('form')?.dispatchEvent(new Event('change', { bubbles: true }));
    });
});

document.querySelectorAll('[data-pn-hours-copy]').forEach((button) => {
    button.addEventListener('click', () => {
        const source = button.closest('[aria-label]')?.querySelector('#hours-copy-source')?.value;
        if (!source) return;
        for (let day = 1; day <= 5; day += 1) copyHours(source, day);
    });
});

const syncTicketOptions = (branchSelect) => {
    const form = branchSelect.closest('form');
    const dateInput = form?.querySelector('[data-pn-ticket-service-date]');
    const branchToday = branchSelect.selectedOptions[0]?.dataset.localToday;

    if (dateInput && branchToday && branchSelect.dataset.previousLocalToday === undefined) {
        branchSelect.dataset.previousLocalToday = branchToday;
        dateInput.addEventListener('input', () => {
            dateInput.dataset.userEdited = 'true';
        });
    }

    form?.querySelectorAll('[data-branch-id]').forEach((option) => {
        const available = option.dataset.branchId === branchSelect.value;
        option.hidden = !available;
        option.disabled = !available || option.dataset.unavailable === 'true';
    });

    form?.querySelectorAll('select[data-pn-ticket-type-select], select[data-pn-ticket-rule-select]').forEach((select) => {
        if (select.selectedOptions[0]?.disabled) {
            select.value = '';
        }
    });

    if (dateInput && branchToday && dateInput.dataset.userEdited !== 'true' && dateInput.value === branchSelect.dataset.previousLocalToday) {
        dateInput.value = branchToday;
    }
    if (branchToday) {
        branchSelect.dataset.previousLocalToday = branchToday;
    }
};

document.querySelectorAll('[data-pn-ticket-branch-select]').forEach((branchSelect) => {
    syncTicketOptions(branchSelect);
    branchSelect.addEventListener('change', () => syncTicketOptions(branchSelect));
});

document.querySelectorAll('[data-pn-checkout-form]').forEach((form) => {
    const method = form.elements.namedItem('verification_method');
    if (!(method instanceof HTMLSelectElement)) return;

    const syncVerificationMethod = () => {
        const managerOverride = method.value === 'manager_override';
        form.querySelectorAll('[data-pn-checkout-verification="guardian"]').forEach((group) => {
            group.hidden = managerOverride;
            group.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = managerOverride;
                field.required = !managerOverride;
            });
        });
        form.querySelectorAll('[data-pn-checkout-verification="override"]').forEach((group) => {
            group.hidden = !managerOverride;
            group.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !managerOverride;
                field.required = managerOverride;
            });
        });
    };

    syncVerificationMethod();
    method.addEventListener('change', syncVerificationMethod);
});

document.querySelectorAll('[data-pn-print-ticket]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

document.querySelectorAll('[data-pn-ticket-qr]').forEach((artifact) => {
    const canvas = artifact.querySelector('[data-pn-ticket-qr-canvas]');
    const fallback = artifact.querySelector('[data-pn-ticket-qr-fallback]');
    const printButton = artifact.querySelector('[data-pn-print-ticket]');
    const payload = artifact.dataset.qrPayload;

    if (!canvas || !payload) {
        canvas?.classList.add('hidden');
        fallback?.classList.remove('hidden');
        printButton?.removeAttribute('disabled');
        return;
    }

    const showFallback = () => {
        canvas.classList.add('hidden');
        fallback?.classList.remove('hidden');
    };

    try {
        QRCode.toCanvas(canvas, payload, {
            width: 320,
            margin: 4,
            color: { dark: '#17262B', light: '#FAFCFC' },
        }, (error) => {
            if (error) {
                showFallback();
            } else {
                canvas.style.width = '100%';
                canvas.style.height = 'auto';
                const bounds = canvas.getBoundingClientRect();
                if (bounds.width <= 0 || Math.abs(bounds.width - bounds.height) > 1) {
                    showFallback();
                }
            }
            printButton?.removeAttribute('disabled');
        });
    } catch {
        showFallback();
        printButton?.removeAttribute('disabled');
    }
});

document.querySelectorAll('[data-pn-confirm-dialog]').forEach((dialog) => {
    const message = dialog.querySelector('[data-pn-confirm-message]');
    const cancel = dialog.querySelector('[data-pn-confirm-cancel]');
    const confirm = dialog.querySelector('[data-pn-confirm-submit]');
    let pendingForm = null;
    let restoreFocus = null;

    const focusable = () => [...dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((element) => !element.disabled && element.offsetParent !== null);

    document.querySelectorAll('[data-pn-confirm-form]').forEach((form) => {
        // Keep native confirmation as a no-JS fallback, not a second dialog.
        form.removeAttribute('onsubmit');
        form.addEventListener('submit', (event) => {
            if (form.dataset.pnConfirmWhen === 'deactivate' && form.elements.is_active?.value !== '0') {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            restoreFocus = event.submitter || form.querySelector('button[type="submit"]');
            if (message) {
                message.textContent = form.dataset.pnConfirmMessage || '';
            }
            dialog.showModal();
            confirm?.focus();
        });
    });

    confirm?.addEventListener('click', () => {
        if (!pendingForm) {
            dialog.close();
            return;
        }

        const form = pendingForm;
        pendingForm = null;
        dialog.close();
        HTMLFormElement.prototype.submit.call(form);
    });

    cancel?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        restoreFocus?.focus();
        restoreFocus = null;
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const elements = focusable();
        if (elements.length === 0) {
            return;
        }
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
