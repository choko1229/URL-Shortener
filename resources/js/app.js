/**
 * 画面のインタラクション（外部ライブラリなし）
 * - 開閉パネル（data-disclosure）
 * - 有効期限の選択（data-expiry-group）
 * - ダイアログ（data-dialog-open）
 * - クリップボードへのコピー（data-copy-text）
 * - メインカラーの入力（data-color-group）
 * - ライト／ダークの切り替え（data-theme-toggle）
 * - 横スクロールするタブの現在地表示（data-scroll-tabs）
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

/** サイト設定のメインカラー: プリセット・色見本・カラーコードの入力を揃える */
function initColorGroups() {
    document.querySelectorAll('[data-color-group]').forEach((group) => {
        const text = group.querySelector('[data-color-text]');
        const picker = group.querySelector('[data-color-picker]');
        const presets = Array.from(group.querySelectorAll('[data-color-preset]'));
        if (!(text instanceof HTMLInputElement)) {
            return;
        }

        const sync = (value) => {
            const color = value.trim().toLowerCase();
            text.value = color;
            if (picker instanceof HTMLInputElement && /^#[0-9a-f]{6}$/.test(color)) {
                picker.value = color;
            }
            presets.forEach((preset) => {
                preset.setAttribute('aria-pressed', String(preset.dataset.colorPreset === color));
            });
        };

        presets.forEach((preset) => {
            preset.addEventListener('click', () => sync(preset.dataset.colorPreset ?? ''));
        });
        picker?.addEventListener('input', () => sync(picker.value));
        text.addEventListener('input', () => sync(text.value));
    });
}

/** ライト／ダークの切り替え（選択はこのブラウザにだけ記憶する） */
function initThemeToggle() {
    const buttons = document.querySelectorAll('[data-theme-toggle]');
    if (buttons.length === 0) {
        return;
    }

    const root = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    const currentTheme = () => root.dataset.theme
        ?? (root.style.colorScheme === 'dark' || getComputedStyle(root).colorScheme === 'dark' ? 'dark' : 'light');

    const sync = () => {
        const dark = currentTheme() === 'dark';
        buttons.forEach((button) => {
            button.setAttribute('aria-label', dark ? 'ライト表示に切り替える' : 'ダーク表示に切り替える');
            button.querySelectorAll('[data-theme-icon]').forEach((icon) => {
                icon.hidden = icon.dataset.themeIcon === (dark ? 'to-dark' : 'to-light');
            });
        });
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const next = currentTheme() === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next;
            try {
                localStorage.setItem('color-theme', next);
            } catch (error) {
                console.warn('[theme] 表示の設定を保存できませんでした。', error);
            }
            sync();
            announce(next === 'dark' ? 'ダーク表示に切り替えました。' : 'ライト表示に切り替えました。');
        });
    });

    // 端末の設定に合わせている間は、その変化にも追従する
    prefersDark.addEventListener('change', () => {
        if (!root.dataset.theme) {
            sync();
        }
    });

    sync();
}

/** 横スクロールするタブで、開いているタブを見える位置に寄せる（項目が多い管理画面向け） */
function initScrollingTabs() {
    document.querySelectorAll('[data-scroll-tabs]').forEach((container) => {
        const current = container.querySelector('[aria-current="page"]');
        if (!(current instanceof HTMLElement) || container.scrollWidth <= container.clientWidth) {
            return;
        }
        // ページ全体は動かさず、この要素の横スクロールだけを変える
        container.scrollLeft = current.offsetLeft - (container.clientWidth - current.offsetWidth) / 2;
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

/** 中間ページ: redirect サブドメインへ自動で POST する（requirements.md 3: 手順 4） */
function initAutoSubmitForms() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        // 中間ページはページ内のスクリプトで送信済み（二重に送らない）
        if (form.dataset.autoSubmitted === 'true') {
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
}

// Google Cloud の reCAPTCHA（スコアベースのキー）用の JavaScript API
const RECAPTCHA_SCRIPT_URL = 'https://www.google.com/recaptcha/enterprise.js';
let recaptchaLoading = null;

function loadRecaptcha(siteKey) {
    if (!recaptchaLoading) {
        recaptchaLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `${RECAPTCHA_SCRIPT_URL}?render=${encodeURIComponent(siteKey)}`;
            script.async = true;
            script.onload = () => window.grecaptcha.enterprise.ready(() => resolve(window.grecaptcha.enterprise));
            script.onerror = () => {
                recaptchaLoading = null;
                reject(new Error('reCAPTCHA のスクリプトを読み込めませんでした。'));
            };
            document.head.append(script);
        });
    }
    return recaptchaLoading;
}

/**
 * reCAPTCHA: 送信ボタンを押した時点でトークンを取得してから送信する（未ログインの発行フォームのみ）。
 * トークンは 2 分で無効になり、1 回しか使えないため、送信のたびに取り直す。
 */
function initRecaptchaForms() {
    document.querySelectorAll('form[data-recaptcha-site-key]').forEach((form) => {
        const siteKey = form.dataset.recaptchaSiteKey ?? '';
        const tokenInput = form.querySelector('[data-recaptcha-token]');
        if (!siteKey || !tokenInput) {
            return;
        }

        // 入力を始めた時点で読み込んでおき、送信時の待ち時間を減らす
        form.addEventListener('focusin', () => loadRecaptcha(siteKey).catch(() => {}), { once: true });

        form.addEventListener('submit', async (event) => {
            // トークンを取得した直後の送信だけを通し、次の送信では取り直す
            if (form.dataset.recaptchaReady === 'true') {
                delete form.dataset.recaptchaReady;
                return;
            }
            event.preventDefault();
            tokenInput.value = '';

            try {
                const grecaptcha = await loadRecaptcha(siteKey);
                tokenInput.value = await grecaptcha.execute(siteKey, { action: form.dataset.recaptchaAction ?? 'submit' });
            } catch (error) {
                // トークン無しで送信し、サーバー側でエラーを表示する
                console.error('[recaptcha]', error);
            }

            form.dataset.recaptchaReady = 'true';
            form.requestSubmit(event.submitter ?? undefined);
        });
    });
}

const SAFETY_CHECK_TIMEOUT_MS = 15000;

/** 転送ページ: 安全性チェックの結果に応じて表示を切り替え、安全なら移動する（requirements.md 2-7, 3） */
function initSafetyCheck() {
    const container = document.querySelector('[data-safety-check]');
    if (!(container instanceof HTMLElement)) {
        return;
    }

    const show = (state, { message = '', threats = [], destination = null } = {}) => {
        container.querySelectorAll('[data-state]').forEach((panel) => {
            panel.hidden = panel.dataset.state !== state;
        });
        const panel = container.querySelector(`[data-state="${state}"]`);
        if (!panel) {
            return;
        }
        panel.querySelectorAll('[data-message]').forEach((element) => {
            element.textContent = message;
        });
        const list = panel.querySelector('[data-threats]');
        if (list) {
            list.replaceChildren(...threats.map((threat) => Object.assign(document.createElement('li'), { textContent: threat })));
        }
        panel.querySelectorAll('[data-destination-link]').forEach((link) => {
            if (destination) {
                link.setAttribute('href', destination);
            }
        });
    };

    const isHttpUrl = (value) => typeof value === 'string' && /^https?:\/\//i.test(value);
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), SAFETY_CHECK_TIMEOUT_MS);

    fetch(container.dataset.checkUrl ?? '', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ ticket: container.dataset.ticket ?? '' }),
        signal: controller.signal,
    })
        .then(async (response) => ({ ok: response.ok, body: await response.json() }))
        .then(({ body }) => {
            const destination = isHttpUrl(body.destination) ? body.destination : null;

            if (body.status === 'safe' && destination) {
                // 待たずにすぐ移動する（表示は移動までのつなぎ）
                show('safe', { destination });
                window.location.replace(destination);
            } else if (body.status === 'unsafe') {
                show('unsafe', { threats: Array.isArray(body.threats) ? body.threats : [] });
            } else if (body.status === 'unknown' && destination) {
                show('unknown', { message: body.message ?? '', destination });
            } else {
                show('invalid', { message: body.message ?? 'リンクをもう一度開いてください。' });
            }
        })
        .catch((error) => {
            // 確認できなかった場合は警告したうえで利用者に任せる（危険判定の場合はここに来ない）
            console.error('[safety-check]', error);
            const destination = container.dataset.destination;
            if (isHttpUrl(destination)) {
                show('unknown', { message: '安全性の確認に時間がかかっているか、通信に失敗しました。', destination });
            } else {
                show('invalid', { message: '元のリンクをもう一度開いてください。' });
            }
        })
        .finally(() => window.clearTimeout(timer));
}

/** 共有時のカード: 「内容を指定する」を選んだときだけ入力欄を表示する */
function initPreviewGroups() {
    document.querySelectorAll('[data-preview-group]').forEach((group) => {
        const radios = Array.from(group.querySelectorAll('[data-preview-mode]'));
        const custom = group.querySelector('[data-preview-custom]');
        const summary = document.getElementById(group.dataset.previewSummary ?? '');

        const sync = () => {
            const selected = radios.find((radio) => radio.checked) ?? null;
            if (custom) {
                custom.hidden = selected?.value !== 'custom';
            }
            if (summary && selected) {
                summary.textContent = selected.dataset.label ?? selected.value;
            }
        };

        radios.forEach((radio) => radio.addEventListener('change', sync));
        sync();
    });
}

/** 一覧の QR ボタン: 共通ダイアログの画像を差し替えて開く */
function initQrDialogs() {
    const dialog = document.getElementById('link-qr-dialog');
    const image = dialog?.querySelector('[data-qr-image]');
    const caption = dialog?.querySelector('[data-qr-caption]');
    if (!(dialog instanceof HTMLDialogElement) || !(image instanceof HTMLImageElement)) {
        return;
    }

    const downloads = {
        svg: dialog.querySelector('[data-qr-download="svg"]'),
        png: dialog.querySelector('[data-qr-download="png"]'),
    };

    document.querySelectorAll('[data-qr-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const label = button.dataset.qrLabel ?? '';
            const svgUrl = button.dataset.qrSrc ?? '';
            image.src = svgUrl;
            image.alt = `${label} のQRコード`;
            if (caption) {
                caption.textContent = label;
            }
            if (downloads.svg instanceof HTMLAnchorElement) {
                downloads.svg.href = svgUrl;
            }
            if (downloads.png instanceof HTMLAnchorElement) {
                downloads.png.href = button.dataset.qrPng ?? '';
            }
            openDialog(dialog);
        });
    });
}

initDisclosures();
initExpiryGroups();
initDialogs();
initPreviewGroups();
initQrDialogs();
initCopyButtons();
initColorGroups();
initThemeToggle();
initScrollingTabs();
initMenus();
initConfirmForms();
initRecaptchaForms();
initAutoSubmitForms();
initSafetyCheck();
