/**
 * Uygulama iskeleti davranışları:
 * tema, üst çubuk, canlı seans halkası ve şimdi çizgisi, hızlı ödeme paneli.
 */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    /* ---------------------------------------------------------------
       Tema (açık / koyu) — çerezde saklanır, sunucu ilk boyamada uygular
       --------------------------------------------------------------- */
    function applyThemeLabels() {
        const dark = document.body.classList.contains('dark');
        $$('[data-theme-label]').forEach(el => { el.textContent = dark ? 'Açık tema' : 'Koyu tema'; });
        $$('[data-theme-icon]').forEach(el => {
            el.classList.toggle('bi-sun', dark);
            el.classList.toggle('bi-moon', !dark);
        });
        const meta = $('meta[name="theme-color"]');
        if (meta && !meta.dataset.fixed) {
            meta.setAttribute('content', dark ? '#151113' : '#EDEBEA');
        }
    }

    $$('[data-theme-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const dark = !document.body.classList.contains('dark');
            document.body.classList.toggle('dark', dark);
            document.cookie = 'theme=' + (dark ? 'dark' : 'light') + '; path=/; max-age=31536000; SameSite=Lax';
            applyThemeLabels();
        });
    });

    /* ---------------------------------------------------------------
       Üst çubuk: kaydırınca ince çizgi
       --------------------------------------------------------------- */
    const appbar = $('[data-appbar]');
    if (appbar) {
        const onScroll = () => appbar.classList.toggle('is-scrolled', window.scrollY > 4);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---------------------------------------------------------------
       Canlı seans halkası (Bugün ekranı)
       data-now-mode: current | next | none
       --------------------------------------------------------------- */
    const now = $('[data-now]');
    if (now) {
        const mode = now.getAttribute('data-now-mode');
        const start = now.getAttribute('data-start') ? new Date(now.getAttribute('data-start')) : null;
        const windowStart = now.getAttribute('data-window-start') ? new Date(now.getAttribute('data-window-start')) : null;
        const length = parseInt(now.getAttribute('data-length') || '50', 10);
        const fill = $('.ring-fill', now);
        const valueEl = $('[data-ring-value]', now);
        const unitEl = $('[data-ring-unit]', now);
        const statusEl = $('[data-now-status]', now);
        const circumference = fill ? 2 * Math.PI * parseFloat(fill.getAttribute('r')) : 0;

        if (fill) {
            fill.style.strokeDasharray = String(circumference);
            fill.style.strokeDashoffset = String(circumference);
        }

        const setRing = (fraction) => {
            if (!fill) return;
            const f = Math.max(0, Math.min(1, fraction));
            fill.style.strokeDashoffset = String(circumference * (1 - f));
        };

        const humanUntil = (mins) => {
            if (mins < 60) return { value: String(mins), unit: 'dk sonra', text: mins <= 1 ? 'birazdan başlıyor' : `${mins} dakika sonra başlıyor` };
            const h = Math.floor(mins / 60);
            const m = mins % 60;
            return {
                value: m === 0 ? `${h} sa` : `${h}:${String(m).padStart(2, '0')}`,
                unit: 'sonra',
                text: m === 0 ? `${h} saat sonra başlıyor` : `${h} saat ${m} dakika sonra başlıyor`
            };
        };

        const tick = () => {
            if (!start || mode === 'none') {
                setRing(0);
                return;
            }
            const nowTime = new Date();
            const diffMin = (start - nowTime) / 60000;

            if (mode === 'next') {
                if (diffMin <= 0) {
                    // Seans başladı: görünümü tazele (açık bir panel yoksa)
                    if (!$('.modal.show') && !$('.offcanvas.show')) window.location.reload();
                    return;
                }
                const mins = Math.ceil(diffMin);
                const h = humanUntil(mins);
                if (valueEl) valueEl.textContent = h.value;
                if (unitEl) unitEl.textContent = h.unit;
                if (statusEl) statusEl.innerHTML = `<strong>${h.text}</strong>`;
                // Halka, önceki seansın bitişinden (ya da bekleme penceresinin başından) bu seansa doğru dolar
                if (windowStart && start > windowStart) {
                    setRing((nowTime - windowStart) / (start - windowStart));
                } else {
                    setRing(1 - Math.min(diffMin, 60) / 60);
                }
            } else if (mode === 'current') {
                const elapsed = -diffMin;
                const left = Math.max(0, Math.ceil(length - elapsed));
                if (elapsed >= length) {
                    if (!$('.modal.show') && !$('.offcanvas.show')) window.location.reload();
                    return;
                }
                if (valueEl) valueEl.textContent = String(left);
                if (unitEl) unitEl.textContent = 'dk kaldı';
                if (statusEl) statusEl.innerHTML = `Seans sürüyor · <strong>${left} dakika kaldı</strong>`;
                setRing(elapsed / length);
            }
        };

        // İlk boyamada halka boş başlar, sonra yerine akar
        requestAnimationFrame(() => requestAnimationFrame(tick));
        setInterval(tick, 20000);
    }

    /* ---------------------------------------------------------------
       Masaüstünde açık gelen açılır bölümler
       --------------------------------------------------------------- */
    if (window.matchMedia('(min-width: 992px)').matches) {
        $$('details[data-open-desktop]').forEach(d => { d.open = true; });
    }

    /* ---------------------------------------------------------------
       Şimdi çizgisi: etiketteki saati güncel tut
       --------------------------------------------------------------- */
    const nowLineLabels = $$('[data-now-clock]');
    if (nowLineLabels.length) {
        const update = () => {
            const d = new Date();
            const text = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
            nowLineLabels.forEach(el => { el.textContent = text; });
            // Boşluktaki çizgi saatin gerçek yerine kayar
            $$('.day-gap[data-gap-from][data-gap-to]').forEach(gap => {
                const line = $('.now-line', gap);
                if (!line) return;
                const from = new Date(gap.getAttribute('data-gap-from'));
                const to = new Date(gap.getAttribute('data-gap-to'));
                const pct = Math.max(0, Math.min(100, (d - from) / (to - from) * 100));
                line.style.top = pct.toFixed(1) + '%';
            });
        };
        update();
        setInterval(update, 20000);
    }

    /* ---------------------------------------------------------------
       Satır eylemleri paneli: listelerde ikincil ve kalıcı işlemler
       (Düzenle, İptal et, Sil…) satırda değil, satıra dokununca açılan
       panelde durur. Birincil eylem (ör. "Ödeme al") satırda kalır.
       Orijinal düğmeler gizli olarak yerinde kalır; panel onları tıklar.
       --------------------------------------------------------------- */
    const PRIMARY = /(^|\s)btn-(primary|brass|success)(\s|$)/;
    let rowSheet = null;

    function ensureRowSheet() {
        if (rowSheet) return rowSheet;
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.id = 'rowActionsModal';
        el.tabIndex = -1;
        el.setAttribute('aria-labelledby', 'rowActionsTitle');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title" id="rowActionsTitle"></h2>
                            <p class="row-meta mb-0" data-row-sheet-meta></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <ul class="menu-list mb-0" data-row-sheet-list></ul>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(el);
        rowSheet = el;
        return el;
    }

    function openRowSheet(row) {
        const sheet = ensureRowSheet();
        const actions = row._sheetActions || [];
        const titleEl = $('.row-title', row);
        const timeEl = $('.row-time', row);
        const metaEl = $('.row-meta', row);
        $('#rowActionsTitle', sheet).textContent = titleEl ? titleEl.textContent.trim() : 'İşlemler';
        const metaParts = [];
        if (timeEl) metaParts.push(timeEl.textContent.replace(/\s+/g, ' ').trim());
        if (metaEl) metaParts.push(metaEl.textContent.replace(/\s+/g, ' ').trim());
        $('[data-row-sheet-meta]', sheet).textContent = metaParts.filter(Boolean).join(' · ');

        const list = $('[data-row-sheet-list]', sheet);
        list.innerHTML = '';
        actions.forEach(original => {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'menu-link';
            const label = original.textContent.replace(/\s+/g, ' ').trim();
            const icon = original.querySelector('i');
            let iconClass = icon ? icon.className : 'bi bi-arrow-right-short';
            if (!icon) {
                if (/düzenle/i.test(label)) iconClass = 'bi bi-pencil';
                else if (/sil/i.test(label)) iconClass = 'bi bi-trash3';
                else if (/[iİ]ptal/i.test(label)) iconClass = 'bi bi-x-circle';
                else if (/durdur/i.test(label)) iconClass = 'bi bi-pause-circle';
                else if (/devam|etkinleştir|başlat/i.test(label)) iconClass = 'bi bi-play-circle';
            }
            btn.innerHTML = `<i class="${iconClass}" aria-hidden="true"></i> <span></span> <i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i>`;
            btn.querySelector('span').textContent = label;
            btn.addEventListener('click', () => {
                const inst = bootstrap.Modal.getOrCreateInstance(sheet);
                sheet.addEventListener('hidden.bs.modal', () => original.click(), { once: true });
                inst.hide();
            });
            li.appendChild(btn);
            list.appendChild(li);
        });
        bootstrap.Modal.getOrCreateInstance(sheet).show();
    }

    $$('.list .row-item').forEach(row => {
        if (row.closest('.modal')) return;
        const holder = $('.row-actions', row);
        if (!holder) return;
        const secondary = $$('button, a.btn', holder).filter(b => !PRIMARY.test(b.className));
        if (!secondary.length) return;

        row._sheetActions = secondary;
        secondary.forEach(b => b.classList.add('d-none'));
        if (!$$('button, a.btn', holder).some(b => !b.classList.contains('d-none'))) {
            holder.classList.add('d-none');
        }

        row.classList.add('has-sheet');
        row.setAttribute('role', 'button');
        row.setAttribute('tabindex', '0');
        row.setAttribute('aria-haspopup', 'dialog');
        const title = $('.row-title', row);
        row.setAttribute('aria-label', (title ? title.textContent.trim() + ': ' : '') + 'işlemler');

        const trail = $('.row-trail', row);
        if (trail && !$('.row-chevron', trail)) {
            trail.insertAdjacentHTML('beforeend', '<i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i>');
        }

        row.addEventListener('click', (e) => {
            if (e.target.closest('a, button, input, select, textarea, label') && e.target.closest('a, button, input, select, textarea, label') !== row) return;
            openRowSheet(row);
        });
        row.addEventListener('keydown', (e) => {
            if (e.target !== row) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openRowSheet(row);
            }
        });
    });

    /* ---------------------------------------------------------------
       Hızlı ödeme paneli: [data-pay] düğmeleri tek bir formu doldurur
       --------------------------------------------------------------- */
    const payModal = document.getElementById('quickPaymentModal');
    if (payModal) {
        payModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger || !trigger.hasAttribute('data-pay')) return;
            const d = trigger.dataset;
            $('[name="appointment_id"]', payModal).value = d.pay;
            $('[data-pay-time]', payModal).textContent = d.payTime || '';
            $('[data-pay-name]', payModal).textContent = d.payName || '';
            $('[data-pay-date]', payModal).textContent = d.payDate || '';
            const amount = $('[name="amount"]', payModal);
            if (amount && d.payAmount) amount.value = d.payAmount;
        });
        // Formu sıfırlayan genel dinleyici gizli alanı da temizler; tutarı varsayılana döndür
        payModal.addEventListener('hidden.bs.modal', () => {
            const amount = $('[name="amount"]', payModal);
            if (amount && amount.dataset.default) amount.value = amount.dataset.default;
        });
    }

    /* ---------------------------------------------------------------
       Telefon: nasıl yazılırsa yazılsın tek biçime (05372212323)
       Sunucudaki includes/phone.php ile aynı kurallar.
       --------------------------------------------------------------- */
    const PHONE_HINT = 'Telefon numarası 10 haneli olmalı; ör. 0537 221 23 23 ya da +90 537 221 23 23.';

    window.normalizePhone = function (raw) {
        let d = String(raw == null ? '' : raw).replace(/\D+/g, '');
        if (!d) return null;
        if (d.startsWith('00')) d = d.slice(2);                       // 0090...
        if (d.length >= 12 && d.startsWith('90')) d = d.slice(2);     // +90 ...
        if (d.length === 11 && d[0] === '0') d = d.slice(1);          // 0537...
        return /^[2-58]\d{9}$/.test(d) ? '0' + d : null;
    };

    function bindPhoneInput(input) {
        if (input.dataset.phoneBound) return;
        input.dataset.phoneBound = '1';

        let feedback = input.parentElement.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.insertAdjacentElement('afterend', feedback);
        }
        const emptyMessage = feedback.textContent.trim() || 'Telefon numarasını girin.';

        const check = (commit) => {
            const value = input.value.trim();
            if (!value) {
                input.setCustomValidity('');
                feedback.textContent = emptyMessage;
                return;
            }
            const normalized = window.normalizePhone(value);
            if (normalized) {
                input.setCustomValidity('');
                feedback.textContent = emptyMessage;
                if (commit) input.value = normalized;
            } else {
                input.setCustomValidity(PHONE_HINT);
                feedback.textContent = PHONE_HINT;
            }
        };

        input.addEventListener('blur', () => check(true));
        input.addEventListener('input', () => check(false));
        // Gönderimden hemen önce (doğrulamadan önce) de uygula
        if (input.form) {
            input.form.addEventListener('submit', () => { if (!input.disabled) check(true); }, true);
        }
    }

    $$('input[name="phone"], input[data-phone]').forEach(bindPhoneInput);

    /* ---------------------------------------------------------------
       ?action=new → yeni randevu panelini aç
       --------------------------------------------------------------- */
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'new') {
        const addModal = document.getElementById('addAppointmentModal');
        if (addModal && window.bootstrap) {
            const dateInput = document.getElementById('date');
            if (dateInput && !dateInput.value) {
                const t = new Date();
                dateInput.value = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
            }
            bootstrap.Modal.getOrCreateInstance(addModal).show();
            addModal.addEventListener('hidden.bs.modal', () => {
                const url = new URL(window.location.href);
                url.searchParams.delete('action');
                window.history.replaceState({}, '', url);
            }, { once: true });
        }
    }

    applyThemeLabels();
})();
