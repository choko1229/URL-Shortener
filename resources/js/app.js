/**
 * chok.ooo の画面インタラクション（外部ライブラリなし）
 * - 開閉パネル（data-disclosure）
 * - 有効期限の選択（data-expiry-group）
 * - ダイアログ（data-dialog-open）
 * - クリップボードへのコピー（data-copy-text）
 * - ユーザーメニュー（data-menu-button）
 * - 送信前の確認（form[data-confirm]）
 */

const COPIED_FEEDBACK_MS = 1600;

/** スクリーンリーダー向けに状態変化を読み上げる */
function announce(message) {
    const region = document.getElementById('live-region');
    if (!region) {
        return;
    }
    region.textContent = '';
    window.setTimeout(() => {
        region.textContent = message;
    }, 50);
}

function openDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) {
        console.error('[dialog] 対象のダイアログが見つかりません。');
        return;
    }
    if (dialog.open) {
        return;
    }
    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }
}

function initDisclosures() {
    document.querySelectorAll('[data-disclosure]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls') ?? '');
        if (!panel) {
            console.warn('[disclosure] 開閉対象のパネルが見つかりません。', button);
            return;
        }

        button.setAttribute('aria-expanded', String(!panel.hidden));
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            panel.hidden = expanded;
        });
    });
}

function initExpiryGroups() {
    document.querySelectorAll('[data-expiry-group]').forEach((group) => {
        const radios = Array.from(group.querySelectorAll('input[type="radio"][name="expiry"]'));
        const customWrapper = group.querySelector('[data-expiry-custom]');
        const customInput = customWrapper?.querySelector('input') ?? null;
        const summary = document.getElementById(group.dataset.expirySummary ?? '');
        let current = radios.find((radio) => radio.checked) ?? null;

        const sync = () => {
            const isCustom = current?.value === 'custom';
            if (customWrapper && customInput) {
                customWrapper.hidden = !isCustom;
                customInput.disabled = !isCustom;
                customInput.required = isCustom;
            }
            if (summary && current) {
                summary.textContent = current.dataset.label ?? current.value;
            }
        };

        radios.forEach((radio) => {
            radio.addEventListener('change', () => {
                const dialogId = radio.dataset.requiresLogin;
                if (dialogId) {
                    // 未ログインで「無期限」を選んだ場合は選択を戻してログインを促す（requirements.md 2-3）
                    radio.checked = false;
                    if (current) {
                        current.checked = true;
                    }
                    openDialog(document.getElementById(dialogId));
                    return;
                }
                current = radio;
                sync();
            });
        });

        sync();
    });
}

function initDialogs() {
    document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openDialog(document.getElementById(trigger.dataset.dialogOpen ?? ''));
        });
    });

    // 背景（ダイアログ外側）のクリックで閉じる
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
}

async function writeClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    // HTTP 環境などで Clipboard API が使えない場合のフォールバック
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.append(textarea);
    textarea.select();
    const succeeded = document.execCommand('copy');
    textarea.remove();

    if (!succeeded) {
        throw new Error('execCommand("copy") が失敗しました。');
    }
}

function initCopyButtons() {
    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-copy-text]') : null;
        if (!(button instanceof HTMLElement)) {
            return;
        }

        try {
            await writeClipboard(button.dataset.copyText ?? '');
            button.dataset.copied = 'true';
            window.setTimeout(() => delete button.dataset.copied, COPIED_FEEDBACK_MS);
            announce('クリップボードにコピーしました。');
        } catch (error) {
            console.error('[copy] コピーに失敗しました。', error);
            announce('コピーできませんでした。テキストを選択してコピーしてください。');
        }
    });
}

function initMenus() {
    document.querySelectorAll('[data-menu-button]').forEach((button) => {
        const menu = document.getElementById(button.getAttribute('aria-controls') ?? '');
        if (!menu) {
            console.warn('[menu] メニューが見つかりません。', button);
            return;
        }

        const setOpen = (open) => {
            menu.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
        };

        button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));

        document.addEventListener('click', (event) => {
            if (!menu.hidden && event.target instanceof Node && !menu.contains(event.target) && !button.contains(event.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) {
                setOpen(false);
                button.focus();
            }
        });

        menu.addEventListener('focusout', (event) => {
            const next = event.relatedTarget;
            if (next instanceof Node && !menu.contains(next) && next !== button) {
                setOpen(false);
            }
        });
    });
}

function initConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}

initDisclosures();
initExpiryGroups();
initDialogs();
initCopyButtons();
initMenus();
initConfirmForms();
