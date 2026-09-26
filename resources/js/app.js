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
 * - 発行時の圧縮アニメーションと効果音（form[data-shorten-form]）
 * - パスワードの表示切り替え・自動生成・安全度（data-password-field）
 * - X に貼ったときの見え方（data-x-card）
 */

const COPIED_FEEDBACK_MS = 1600;

/*
 * 効果音（Web Audio で合成するため音声ファイルは不要）。
 * ブラウザーは操作（クリックなど）のあとでないと音を出さないため、操作に応じて鳴らす音だけを用意する。
 */
let audioContext = null;

function getAudioContext() {
    const AudioContextClass = window.AudioContext ?? window.webkitAudioContext;
    if (!AudioContextClass) {
        return null;
    }
    audioContext ??= new AudioContextClass();
    if (audioContext.state === 'suspended') {
        audioContext.resume().catch(() => {});
    }
    return audioContext;
}

/** 音程が変わる短い音 */
function playTone(context, { type = 'sine', from, to = from, start = 0, duration, volume = 0.1 }) {
    const startAt = context.currentTime + start;
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(from, startAt);
    if (to !== from) {
        oscillator.frequency.exponentialRampToValueAtTime(to, startAt + duration);
    }
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.connect(gain).connect(context.destination);
    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.02);
}

/** 空気が抜けるような「しゅっ」という音（ノイズの高さを下げていく） */
function playWhoosh(context, { start = 0, duration, from, to, volume = 0.15 }) {
    const startAt = context.currentTime + start;
    const buffer = context.createBuffer(1, Math.ceil(context.sampleRate * duration), context.sampleRate);
    const samples = buffer.getChannelData(0);
    for (let i = 0; i < samples.length; i++) {
        samples[i] = Math.random() * 2 - 1;
    }

    const source = context.createBufferSource();
    const filter = context.createBiquadFilter();
    const gain = context.createGain();

    source.buffer = buffer;
    filter.type = 'bandpass';
    filter.Q.value = 1.4;
    filter.frequency.setValueAtTime(from, startAt);
    filter.frequency.exponentialRampToValueAtTime(to, startAt + duration);
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + duration * 0.3);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    source.connect(filter).connect(gain).connect(context.destination);
    source.start(startAt);
    source.stop(startAt + duration);
}

const SOUNDS = {
    // 「ぎゅーっ」と縮んで「ぽんっ」
    compress: (context) => {
        playWhoosh(context, { duration: 0.45, from: 3200, to: 380, volume: 0.16 });
        playTone(context, { type: 'triangle', from: 560, to: 130, duration: 0.45, volume: 0.07 });
        playTone(context, { type: 'sine', from: 660, to: 1320, start: 0.48, duration: 0.14, volume: 0.13 });
    },
    // 「ピコッ」
    copy: (context) => {
        playTone(context, { type: 'triangle', from: 988, duration: 0.07, volume: 0.09 });
        playTone(context, { type: 'triangle', from: 1480, start: 0.065, duration: 0.14, volume: 0.09 });
    },
};

function playSound(name) {
    try {
        const context = getAudioContext();
        if (context) {
            SOUNDS[name]?.(context);
        }
    } catch (error) {
        // 音が出せなくても操作は続ける
        console.warn('[sound] 効果音を再生できませんでした。', error);
    }
}

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

const EXPIRY_DAYS = { '1d': 1, '7d': 7, '30d': 30 };
const EXPIRY_REFRESH_MS = 30000;

/** 日時を「2026/10/27（火）15:30」の形にする（サイトの表示タイムゾーン） */
function formatDateTime(date, timeZone) {
    const parts = {};
    new Intl.DateTimeFormat('ja-JP', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date).forEach(({ type, value }) => {
        parts[type] = value;
    });
    return `${parts.year}/${parts.month}/${parts.day}（${parts.weekday}）${parts.hour}:${parts.minute}`;
}

/** datetime-local の値（表示タイムゾーンの壁時計）を同じ形に整える */
function formatLocalInput(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value);
    if (!match) {
        return null;
    }
    const [, year, month, day, hour, minute] = match;
    const weekday = new Intl.DateTimeFormat('ja-JP', { weekday: 'short', timeZone: 'UTC' })
        .format(new Date(Date.UTC(Number(year), Number(month) - 1, Number(day))));
    return `${year}/${month}/${day}（${weekday}）${hour}:${minute}`;
}

function initExpiryGroups() {
    document.querySelectorAll('[data-expiry-group]').forEach((group) => {
        const radios = Array.from(group.querySelectorAll('input[type="radio"][name="expiry"]'));
        const customWrapper = group.querySelector('[data-expiry-custom]');
        const customInput = customWrapper?.querySelector('input') ?? null;
        const summary = document.getElementById(group.dataset.expirySummary ?? '');
        const until = group.querySelector('[data-expiry-until]');
        const timeZone = group.dataset.timezone || 'Asia/Tokyo';
        const zoneLabel = timeZone === 'Asia/Tokyo' ? '日本時間' : timeZone;
        let current = radios.find((radio) => radio.checked) ?? null;

        /** 今発行した場合の期限。[本文, チップに添える短い表記] */
        const describeUntil = () => {
            const value = current?.value;
            if (value === 'never') {
                return ['期限なし（削除するまで使えます）', ''];
            }
            if (value === 'custom') {
                const formatted = customInput ? formatLocalInput(customInput.value) : null;
                return formatted
                    ? [`${formatted} まで有効（${zoneLabel}）`, formatted.replace(/（.）/, ' ')]
                    : ['日時を選ぶと、ここにいつまで有効かが表示されます。', ''];
            }
            const days = EXPIRY_DAYS[value ?? ''];
            if (!days) {
                return ['', ''];
            }
            const formatted = formatDateTime(new Date(Date.now() + days * 86400000), timeZone);
            return [`今発行すると ${formatted} まで有効（${zoneLabel}）`, formatted.replace(/^\d{4}\//, '').replace(/（.）/, ' ')];
        };

        const sync = () => {
            const isCustom = current?.value === 'custom';
            if (customWrapper && customInput) {
                customWrapper.hidden = !isCustom;
                customInput.disabled = !isCustom;
                customInput.required = isCustom;
            }
            const [sentence, short] = describeUntil();
            if (summary && current) {
                const label = current.dataset.label ?? current.value;
                summary.textContent = short ? `${label}（${short} まで）` : label;
            }
            if (until) {
                until.textContent = sentence;
            }
        };

        customInput?.addEventListener('input', sync);
        // 固定期間は「今」からの計算なので、開いたままでも時刻を進める
        window.setInterval(sync, EXPIRY_REFRESH_MS);

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
            // 続けて押したときもアニメーションを最初から見せる
            window.clearTimeout(Number(button.dataset.copiedTimer ?? 0));
            delete button.dataset.copied;
            void button.offsetWidth;
            button.dataset.copied = 'true';
            button.dataset.copiedTimer = String(window.setTimeout(() => delete button.dataset.copied, COPIED_FEEDBACK_MS));
            playSound('copy');
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

const COMPRESS_ANIMATION_MS = 650;

/**
 * 発行フォーム: 送信するときに入力欄を押しつぶすアニメーションと効果音を見せてから送る。
 * reCAPTCHA の処理（initRecaptchaForms）より先に登録し、演出が終わってから送信の流れに戻す。
 */
function initShortenForms() {
    document.querySelectorAll('form[data-shorten-form]').forEach((form) => {
        let animating = false;
        const submitButton = form.querySelector('[data-shorten-submit]');

        form.addEventListener('submit', (event) => {
            if (form.dataset.compressed === 'true') {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (animating) {
                // 演出中の連打は無視する（二重送信の防止）
                return;
            }

            animating = true;
            form.classList.add('is-compressing');
            submitButton?.setAttribute('aria-busy', 'true');
            playSound('compress');
            announce('短縮URLを発行しています。');

            const submitter = event.submitter;
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.setTimeout(() => {
                form.dataset.compressed = 'true';
                if (submitter instanceof HTMLElement && submitter.form === form) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
            }, reducedMotion ? 0 : COMPRESS_ANIMATION_MS);
        });

        // 「戻る」でこのページに戻ってきたときは元の状態に戻す
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                animating = false;
                delete form.dataset.compressed;
                form.classList.remove('is-compressing');
                submitButton?.removeAttribute('aria-busy');
            }
        });
    });
}

const GENERATED_PASSWORD_LENGTH = 12;
// 読み間違えやすい文字（l / I / O / 0 / 1）は除く
const PASSWORD_CHARSETS = [
    'abcdefghijkmnopqrstuvwxyz',
    'ABCDEFGHJKLMNPQRSTUVWXYZ',
    '23456789',
    '!#$%&*+-=?@^_',
];

/** 0 以上 max 未満の偏りのない乱数 */
function randomIndex(max) {
    const limit = Math.floor(0x100000000 / max) * max;
    const buffer = new Uint32Array(1);
    do {
        crypto.getRandomValues(buffer);
    } while (buffer[0] >= limit);
    return buffer[0] % max;
}

/** 英小文字・英大文字・数字・記号をそれぞれ 1 文字以上含む 12 文字 */
function generatePassword() {
    const all = PASSWORD_CHARSETS.join('');
    const characters = PASSWORD_CHARSETS.map((set) => set[randomIndex(set.length)]);
    while (characters.length < GENERATED_PASSWORD_LENGTH) {
        characters.push(all[randomIndex(all.length)]);
    }
    for (let i = characters.length - 1; i > 0; i--) {
        const j = randomIndex(i + 1);
        [characters[i], characters[j]] = [characters[j], characters[i]];
    }
    return characters.join('');
}

const COMMON_PASSWORDS = ['password', 'passw0rd', 'qwerty', 'letmein', 'welcome', 'admin', 'iloveyou', 'abc123', 'monkey', 'dragon', 'secret'];
const STRENGTH_LEVELS = [
    { label: '弱い', bar: 'bg-danger', text: 'text-danger' },
    { label: '普通', bar: 'bg-warning', text: 'text-warning' },
    { label: '強い', bar: 'bg-primary', text: 'text-primary-dark' },
    { label: 'とても強い', bar: 'bg-primary-dark', text: 'text-primary-dark' },
];

/**
 * パスワードの安全度の目安（0〜3）。文字の種類と長さから推測しにくさを見積もり、
 * よく使われる語や同じ文字・連番の繰り返しは弱く見る。入力を制限するものではない。
 */
function passwordStrength(password) {
    let pool = 0;
    if (/[a-z]/.test(password)) pool += 26;
    if (/[A-Z]/.test(password)) pool += 26;
    if (/[0-9]/.test(password)) pool += 10;
    if (/[^a-zA-Z0-9]/.test(password)) pool += 33;

    let bits = [...password].length * Math.log2(Math.max(pool, 1));
    const lower = password.toLowerCase();
    const hints = [];

    if (COMMON_PASSWORDS.some((word) => lower.includes(word)) || /^(\d)\1+$|^(0123|1234|2345|3456|4567|5678|6789)/.test(password)) {
        bits = Math.min(bits, 20);
        hints.push('よく使われる文字列が含まれています');
    }
    if (/(.)\1{2,}/.test(password)) {
        bits *= 0.75;
        hints.push('同じ文字の繰り返しは避けましょう');
    }
    if ([...password].length < 12) {
        hints.push('12文字以上にするとより安全です');
    }
    if (pool < 60) {
        hints.push('大文字・数字・記号を混ぜるとより安全です');
    }

    const level = bits < 30 ? 0 : bits < 50 ? 1 : bits < 70 ? 2 : 3;
    return { level, hint: level < 3 ? hints[0] ?? '' : '' };
}

/** アクセス用パスワード: 表示切り替え・自動生成・安全度の表示 */
function initPasswordFields() {
    document.querySelectorAll('[data-password-field]').forEach((field) => {
        const input = field.querySelector('[data-password-input]');
        const reveal = field.querySelector('[data-password-reveal]');
        const generate = field.querySelector('[data-password-generate]');
        const meter = field.querySelector('[data-password-strength]');
        const bars = Array.from(field.querySelectorAll('[data-strength-bar]'));
        const label = field.querySelector('[data-strength-label]');
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const setVisible = (visible) => {
            input.type = visible ? 'text' : 'password';
            if (reveal) {
                const text = visible ? 'パスワードを隠す' : 'パスワードを表示する';
                reveal.setAttribute('aria-pressed', String(visible));
                reveal.setAttribute('aria-label', text);
                reveal.setAttribute('title', text);
                reveal.querySelectorAll('[data-reveal-icon]').forEach((icon) => {
                    icon.toggleAttribute('hidden', icon.dataset.revealIcon !== (visible ? 'hide' : 'show'));
                });
            }
        };

        const updateStrength = () => {
            if (!meter || !label) {
                return;
            }
            const password = input.value;
            meter.hidden = password === '';
            if (password === '') {
                label.textContent = '';
                return;
            }

            const { level, hint } = passwordStrength(password);
            const current = STRENGTH_LEVELS[level];
            bars.forEach((bar, index) => {
                STRENGTH_LEVELS.forEach(({ bar: color }) => bar.classList.remove(color));
                bar.classList.toggle('bg-border-input', index > level);
                if (index <= level) {
                    bar.classList.add(current.bar);
                }
            });
            label.className = `mt-1.5 text-xs ${current.text}`;
            label.textContent = `安全度: ${current.label}${hint ? `（${hint}）` : ''}`;
        };

        reveal?.addEventListener('click', () => setVisible(input.type === 'password'));
        generate?.addEventListener('click', () => {
            input.value = generatePassword();
            // 生成した値は控えられるよう表示しておく
            setVisible(true);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
            input.select();
            announce('12文字のパスワードを生成しました。');
        });
        input.addEventListener('input', updateStrength);
        updateStrength();
    });
}

const CARD_FETCH_DELAY_MS = 600;

/**
 * 共有時のカード: X に貼ったときの見え方（イメージ）を、同じフォームの入力から組み立てる。
 * 「転送先のカードを見せる」ときは、転送先ページのカード情報をサーバー経由で取得する。
 */
function initXCardPreviews() {
    document.querySelectorAll('[data-x-card]').forEach((card) => {
        const form = card.closest('form');
        if (!form) {
            return;
        }

        const field = (name) => form.querySelector(`[name="${name}"]`);
        const urlInput = field('original_url');
        const slugInput = field('custom_slug');
        const passwordInput = field('password');
        const titleInput = field('preview_title');
        const descriptionInput = field('preview_description');
        const imageInput = field('preview_image_url');
        const token = field('_token')?.value ?? '';

        const post = card.querySelector('[data-x-card-post]');
        const large = card.querySelector('[data-x-card-large]');
        const small = card.querySelector('[data-x-card-small]');
        const status = card.querySelector('[data-x-card-status]');
        const siteName = card.dataset.siteName ?? '';
        const shortHost = card.dataset.shortHost ?? '';
        const serviceCard = { title: siteName, description: `${siteName} で短縮されたリンクです。`, image: null, large: false };

        const fetched = new Map();
        let timer = 0;
        let visible = false;

        const isHttpUrl = (value) => {
            try {
                return ['http:', 'https:'].includes(new URL(value).protocol);
            } catch {
                return false;
            }
        };

        const currentUrl = () => (card.dataset.destination ?? urlInput?.value ?? '').trim();

        const setText = (container, selector, text) => {
            container.querySelectorAll(selector).forEach((element) => {
                element.textContent = text ?? '';
                element.hidden = !text;
            });
        };

        const showCard = (data) => {
            large.hidden = true;
            small.hidden = true;
            if (!data) {
                return;
            }

            const target = data.large && data.image ? large : small;
            target.hidden = false;
            setText(target, '[data-x-card-title]', data.title);
            setText(target, '[data-x-card-description]', data.description);
            setText(target, '[data-x-card-domain]', shortHost);
            setText(target, '[data-x-card-from]', `${shortHost} から`);

            const image = target.querySelector('[data-x-card-image]');
            const placeholder = target.querySelector('[data-x-card-placeholder]');
            const showPlaceholder = (show) => {
                image.hidden = show;
                placeholder?.toggleAttribute('hidden', !show);
            };
            if (data.image) {
                showPlaceholder(false);
                image.onerror = () => {
                    // 画像を読み込めない場合、X でも画像なしのカードになる
                    if (target === large) {
                        showCard({ ...data, large: false, image: null });
                    } else {
                        showPlaceholder(true);
                    }
                };
                image.src = data.image;
            } else {
                image.removeAttribute('src');
                showPlaceholder(true);
            }
        };

        const fetchDestination = async (url) => {
            fetched.set(url, { state: 'loading' });
            try {
                const response = await fetch(card.dataset.fetchUrl ?? '', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ url }),
                });
                if (response.status === 429) {
                    fetched.delete(url);
                    return { state: 'error', message: '続けて確認したため、しばらくしてからもう一度お試しください。' };
                }
                if (!response.ok) {
                    return { state: 'error', message: '転送先のURLを確認できませんでした。' };
                }
                const body = await response.json();
                return { state: 'done', card: body.card ?? null };
            } catch (error) {
                console.warn('[x-card] 転送先のカードを取得できませんでした。', error);
                fetched.delete(url);
                return { state: 'error', message: '転送先のカードを取得できませんでした。通信状況を確認してください。' };
            }
        };

        const render = () => {
            const slug = card.dataset.slug ?? (slugInput?.value.trim() || 'xxxxxx');
            post.textContent = `${shortHost}/${slug}`;

            const selected = form.querySelector('[name="preview_mode"]:checked')?.value ?? 'destination';
            const hidden = card.dataset.passwordProtected === 'true' || (passwordInput?.value ?? '') !== '';
            const mode = hidden ? 'service' : selected;

            if (mode === 'service') {
                showCard(serviceCard);
                status.textContent = hidden
                    ? 'パスワード保護つきのため、サービス名だけのカードになります。'
                    : '転送先は表示されず、サービス名だけのカードになります。';
                return;
            }

            if (mode === 'custom') {
                const image = imageInput && isHttpUrl(imageInput.value.trim()) ? imageInput.value.trim() : null;
                showCard({
                    title: titleInput?.value.trim() || siteName,
                    description: descriptionInput?.value.trim() || serviceCard.description,
                    image,
                    large: image !== null,
                });
                status.textContent = '入力した内容でカードが表示されます。';
                return;
            }

            const url = currentUrl();
            if (!isHttpUrl(url)) {
                showCard(null);
                status.textContent = '転送先のURLを入力すると、ここに見え方が表示されます。';
                return;
            }

            const result = fetched.get(url);
            if (!result) {
                showCard(null);
                status.textContent = '転送先のカードを確認しています…';
                if (visible) {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(async () => {
                        if (fetched.has(url)) {
                            return;
                        }
                        const outcome = await fetchDestination(url);
                        if (fetched.has(url)) {
                            fetched.set(url, outcome);
                            render();
                        } else if (currentUrl() === url) {
                            // 一時的な失敗は覚えず、入力し直したときにもう一度問い合わせる
                            status.textContent = outcome.message;
                        }
                    }, CARD_FETCH_DELAY_MS);
                }
                return;
            }

            if (result.state === 'loading') {
                status.textContent = '転送先のカードを確認しています…';
                return;
            }
            if (result.state === 'error') {
                showCard(null);
                status.textContent = result.message;
                return;
            }
            showCard(result.card);
            status.textContent = result.card
                ? '転送先ページのカードがそのまま表示されます（転送先の変更により変わることがあります）。'
                : '転送先ページにカードの情報が見つかりませんでした。X ではリンクだけが表示されることがあります。';
        };

        form.addEventListener('input', render);
        form.addEventListener('change', render);

        // 開いているときだけ転送先へ問い合わせる
        if ('IntersectionObserver' in window) {
            new IntersectionObserver((entries) => {
                visible = entries.some((entry) => entry.isIntersecting);
                if (visible) {
                    render();
                }
            }).observe(card);
        } else {
            visible = true;
        }
        render();
    });
}

initDisclosures();
initExpiryGroups();
initPasswordFields();
initXCardPreviews();
initShortenForms();
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
