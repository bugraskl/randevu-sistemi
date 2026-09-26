document.addEventListener('DOMContentLoaded', function() {
    // Not: Menü, sekme çubuğu ve tema değiştirme artık assets/js/app.js içinde.

    // Form doğrulama
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Kaydediliyor...';
                }
            }
            form.classList.add('was-validated');
        });
    });

    // Modal kapanma olaylarını dinle
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.getAttribute('data-original-text') || 'Kaydet';
                }
            }
        });
    });

    // Submit butonlarının orijinal metinlerini sakla
    document.querySelectorAll('button[type="submit"]').forEach(button => {
        button.setAttribute('data-original-text', button.innerHTML);
    });

    // Sayfa bazlı initialization
    const currentPage = document.body.getAttribute('data-page') || window.location.pathname.split('/').pop().replace('.php', '');

    switch(currentPage) {
        case 'clients':
            window.initializeClientPage();
            break;
        case 'appointments':
            window.initializeAppointmentsPage();
            break;
        case 'expenses':
            window.initializeExpensesPage && window.initializeExpensesPage();
            break;
        // Diğer sayfalar için case'ler eklenebilir
    }
});

// HTML kaçış (sunucudan gelen metinleri güvenle yazmak için)
window.escapeHtml = function(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

// Telefonu okunaklı göster: 05001234567 → 0500 123 45 67 (includes/phone.php formatPhoneDisplay ile aynı)
window.formatPhoneDisplay = function(phone) {
    let d = String(phone == null ? '' : phone).replace(/\D+/g, '');
    if (d.startsWith('00')) d = d.slice(2);
    if (d.length >= 12 && d.startsWith('90')) d = d.slice(2);
    if (d.length === 11 && d[0] === '0') d = d.slice(1);
    if (!/^[2-58]\d{9}$/.test(d)) return String(phone == null ? '' : phone);
    d = '0' + d;
    return `${d.slice(0, 4)} ${d.slice(4, 7)} ${d.slice(7, 9)} ${d.slice(9, 11)}`;
};

// Randevu durumu → tek durum dili
window.appointmentStatusMark = function(apt) {
    const esc = window.escapeHtml;
    const past = new Date(apt.appointment_date + 'T' + apt.appointment_time) < new Date();
    if (apt.status === 'iptal') return '<span class="mark mark-cancelled">İptal edildi</span>';
    if (past) return apt.payment_id ? '<span class="mark mark-paid">Ödendi</span>' : '<span class="mark mark-unpaid">Ödenmedi</span>';
    // Randevular onay beklemez: gelecekteki randevu için ayrı bir durum işareti yok
    return '';
};

// ---------------------------------------------------------------------------
// Randevu düzenleme / silme: tek ortak panel, window.appointments verisiyle dolar
// ---------------------------------------------------------------------------
const TR_MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
const TR_DAYS = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

function todayKey() {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
}

function longDate(key) {
    const [y, m, d] = key.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    return `${d} ${TR_MONTHS[m - 1]} ${TR_DAYS[date.getDay()]}`;
}

window.findAppointment = function(id) {
    return (window.appointments || []).find(a => String(a.id) === String(id)) || null;
};

// Geçmiş tarihli randevular düzenleme formunda kaydedilemez (sunucu reddeder)
window.canEditAppointment = function(apt) {
    return !!apt && apt.appointment_date >= todayKey() && !!document.getElementById('editAppointmentModal');
};

function fillAppointmentSummary(prefix, apt) {
    document.querySelectorAll(`[data-${prefix}-time]`).forEach(el => { el.textContent = apt.formatted_time || String(apt.appointment_time).slice(0, 5); });
    document.querySelectorAll(`[data-${prefix}-name]`).forEach(el => { el.textContent = apt.client_name || ''; });
    document.querySelectorAll(`[data-${prefix}-date]`).forEach(el => { el.textContent = longDate(apt.appointment_date); });
}

window.openAppointmentEditor = function(id) {
    const apt = window.findAppointment(id);
    if (!window.canEditAppointment(apt)) {
        window.showToastMessage('Geçmiş tarihli randevular düzenlenemez.', 'warning');
        return;
    }
    const modal = document.getElementById('editAppointmentModal');
    fillAppointmentSummary('edit', apt);
    fillAppointmentSummary('delete', apt);

    const time = String(apt.appointment_time).slice(0, 5);
    const [hh, mm] = time.split(':');
    document.getElementById('editAppointmentId').value = apt.id;
    document.getElementById('deleteAppointmentId').value = apt.id;
    document.getElementById('editAppointmentClient').value = String(apt.client_id);
    document.getElementById('editAppointmentDate').value = apt.appointment_date;
    document.getElementById('editAppointmentHour').value = hh;
    const minuteSelect = document.getElementById('editAppointmentMinute');
    if (!Array.from(minuteSelect.options).some(o => o.value === mm)) {
        minuteSelect.add(new Option(mm, mm));
    }
    minuteSelect.value = mm;
    document.getElementById('editAppointmentNotes').value = apt.notes || '';

    const tel = modal.querySelector('[data-edit-tel]');
    const digits = String(apt.client_phone || '').replace(/[^0-9+]/g, '');
    tel.hidden = !digits;
    tel.href = digits ? 'tel:' + digits : '#';

    bootstrap.Modal.getOrCreateInstance(modal).show();
};

window.openAppointmentDelete = function(id) {
    const apt = window.findAppointment(id);
    if (!window.canEditAppointment(apt)) {
        window.showToastMessage('Geçmiş tarihli randevular bu ekrandan silinemez.', 'warning');
        return;
    }
    fillAppointmentSummary('delete', apt);
    document.getElementById('deleteAppointmentId').value = apt.id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteAppointmentModal')).show();
};

// Toast mesajları için global fonksiyon
window.showToastMessage = function(message, type) {
    if (window.toast && typeof window.toast.show === 'function') {
        window.toast.show(message, type);
    }
};

// Expenses sayfası init (ileride filtre/arama eklemek için placeholder)
window.initializeExpensesPage = function() {
    // Gelecekte gelişmiş filtre/arama için burada JS yazılabilir
};

// Global mesaj fonksiyonları (PHP session mesajları için)
window.showSessionMessages = function() {
    // Bu fonksiyon PHP tarafından çağrılacak
    const successMsg = window.sessionSuccess;
    const errorMsg = window.sessionError;
    const warningMsg = window.sessionWarning;

    if (successMsg) {
        window.showToastMessage(successMsg, 'success');
    }
    if (errorMsg) {
        window.showToastMessage(errorMsg, 'error');
    }
    if (warningMsg) {
        window.showToastMessage(warningMsg, 'warning');
    }
};

// Tablo satırlarını arama fonksiyonu (gelişmiş)
window.searchTableRows = function(searchTerm, tableSelector = 'tbody tr') {
    const rows = document.querySelectorAll(tableSelector);
    const term = searchTerm.toLowerCase().trim();

    rows.forEach(row => {
        let found = false;
        const cells = row.querySelectorAll('td');

        cells.forEach(cell => {
            if (cell.textContent.toLowerCase().includes(term)) {
                found = true;
            }
        });

        row.style.display = found ? '' : 'none';
    });
};

// Form resetleme fonksiyonu
window.resetForm = function(formSelector) {
    const form = document.querySelector(formSelector);
    if (form) {
        form.reset();
        form.classList.remove('was-validated');

        // Hata mesajlarını temizle
        const invalidInputs = form.querySelectorAll('.is-invalid');
        invalidInputs.forEach(input => {
            input.classList.remove('is-invalid');
        });
    }
};

// Loading state yönetimi
window.setLoadingState = function(button, loading = true) {
    if (!button) return;

    if (loading) {
        button.disabled = true;
        const originalText = button.getAttribute('data-original-text') || button.innerHTML;
        button.setAttribute('data-original-text', originalText);
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Yükleniyor...';
    } else {
        button.disabled = false;
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.innerHTML = originalText;
        }
    }
};

// Sayfa yenileme öncesi uyarı
window.confirmPageLeave = function(message = 'Değişiklikler kaydedilmemiş olabilir. Sayfadan ayrılmak istediğinizden emin misiniz?') {
    window.addEventListener('beforeunload', function(e) {
        if (document.querySelector('.was-validated') || document.querySelector('input:invalid')) {
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });
};

// Numeric input formatters
window.formatCurrency = function(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value) {
        value = parseInt(value).toLocaleString('tr-TR');
        input.value = value;
    }
};

window.formatPhone = function(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value.length >= 10) {
        value = value.substring(0, 11);
        if (value.startsWith('0')) {
            value = value.replace(/(\d{1})(\d{3})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
        }
        input.value = value;
    }
};

// Client arama fonksiyonu
window.performClientSearch = function(page = 1) {
    const esc = window.escapeHtml;
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const searchResultsModal = document.getElementById('searchResultsModal');
    const searchResults = document.getElementById('searchResults');

    if (!searchInput || !searchResults) return;

    // Tıklama olayından gelen parametreyi yok say
    if (typeof page !== 'number') page = 1;

    const searchTerm = searchInput.value.trim();
    if (searchTerm.length < 2) {
        window.showToastMessage('Lütfen en az 2 karakter giriniz.', 'warning');
        return;
    }

    // Loading göster (sadece ilk sayfa için buton loading'i)
    if (page === 1) {
        window.setLoadingState(searchButton, true);
    }

    fetch(`process/search-clients?term=${encodeURIComponent(searchTerm)}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Modal başlığını güncelle
                const modalTitle = searchResultsModal.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.textContent = `“${data.search_term}” için ${data.pagination.total_records} sonuç`;
                }

                // Sonuçları temizle
                searchResults.innerHTML = '';

                if (data.clients.length === 0 && page === 1) {
                    searchResults.innerHTML = `
                        <div class="empty">
                            <p class="empty-title">Danışan bulunamadı</p>
                            <p>“${esc(data.search_term)}” ile eşleşen bir danışan yok. Adı ya da telefonun bir kısmıyla tekrar deneyin.</p>
                        </div>
                    `;
                } else {
                    const list = document.createElement('div');
                    list.className = 'list';
                    data.clients.forEach(client => {
                        const item = document.createElement('div');
                        item.className = 'row-item no-lead';
                        item.innerHTML = `
                            <a class="row-main text-decoration-none" href="client-details?id=${encodeURIComponent(client.id)}">
                                <p class="row-title"><span>${esc(client.name)}</span></p>
                                <p class="row-meta tnum">${esc(window.formatPhoneDisplay(client.phone))}${client.email ? ' · ' + esc(client.email) : ''}</p>
                            </a>
                            <div class="row-trail">
                                <span class="badge bg-secondary">${esc(client.appointment_count)} randevu</span>
                            </div>
                            <div class="row-actions">
                                <a href="client-details?id=${encodeURIComponent(client.id)}" class="btn btn-sm btn-secondary">Kartı aç</a>
                                <button type="button" class="btn btn-sm btn-secondary" data-edit-client="${esc(client.id)}">Düzenle</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-delete-client="${esc(client.id)}" data-client-name="${esc(client.name)}">Sil</button>
                            </div>
                        `;
                        list.appendChild(item);
                    });
                    searchResults.appendChild(list);

                    list.querySelectorAll('[data-edit-client]').forEach(btn => {
                        btn.addEventListener('click', () => window.editClientFromSearch(btn.getAttribute('data-edit-client')));
                    });
                    list.querySelectorAll('[data-delete-client]').forEach(btn => {
                        btn.addEventListener('click', () => window.deleteClientFromSearch(btn.getAttribute('data-delete-client'), btn.getAttribute('data-client-name')));
                    });

                    // Sayfalama ekle
                    if (data.pagination.total_pages > 1) {
                        const paginationDiv = document.createElement('div');
                        paginationDiv.className = 'mt-3 d-flex justify-content-between align-items-center gap-2';
                        paginationDiv.innerHTML = `
                            <small class="tnum">Sayfa ${data.pagination.current_page} / ${data.pagination.total_pages}</small>
                            <div class="d-flex gap-2">
                                ${data.pagination.has_prev ?
                                    `<button type="button" class="btn btn-sm btn-secondary" data-page-to="${data.pagination.current_page - 1}">
                                        <i class="bi bi-chevron-left" aria-hidden="true"></i> Önceki
                                    </button>` : ''
                                }
                                ${data.pagination.has_next ?
                                    `<button type="button" class="btn btn-sm btn-secondary" data-page-to="${data.pagination.current_page + 1}">
                                        Sonraki <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                    </button>` : ''
                                }
                            </div>
                        `;
                        paginationDiv.querySelectorAll('[data-page-to]').forEach(btn => {
                            btn.addEventListener('click', () => window.performClientSearch(parseInt(btn.getAttribute('data-page-to'), 10)));
                        });
                        searchResults.appendChild(paginationDiv);
                    }
                }

                // Modal göster (sadece ilk sayfa için)
                if (page === 1 && searchResultsModal && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(searchResultsModal).show();
                }
            } else {
                window.showToastMessage(data.error || data.message || 'Arama sırasında bir hata oluştu.', 'error');
            }
        })
        .catch(error => {
            console.error('Arama hatası:', error);
            window.showToastMessage('Arama sırasında bir hata oluştu.', 'error');
        })
        .finally(() => {
            if (page === 1) {
                window.setLoadingState(searchButton, false);
            }
        });
};

// Client sayfası için event listener'ları
window.initializeClientPage = function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    let searchTimeout;

    if (searchButton) {
        searchButton.addEventListener('click', () => window.performClientSearch(1));
    }

    if (searchInput) {
        // Enter tuşu ile arama
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                window.performClientSearch(1);
            }
        });

        // Anlık arama (typing sırasında)
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();

            // Eğer 2 karakterden azsa arama yapma
            if (searchTerm.length < 2) {
                return;
            }

            // 500ms bekle, ardından arama yap
            searchTimeout = setTimeout(() => {
                window.performClientSearch(1);
            }, 500);
        });
    }
};

// Appointments sayfası için fonksiyonlar
window.initializeAppointmentsPage = function() {
    const searchButton = document.getElementById('searchButton');
    const searchDate = document.getElementById('searchDate');

    // Tarih arama
    if (searchButton && searchDate) {
        searchButton.addEventListener('click', function() {
            const selectedDate = searchDate.value;
            if (selectedDate) {
                window.performAppointmentSearch(selectedDate);
            } else {
                window.showToastMessage('Lütfen bir tarih seçiniz.', 'warning');
            }
        });

        // Enter tuşu ile arama
        searchDate.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchButton.click();
            }
        });
    }

    // Liste satırı → ortak düzenleme paneli
    document.querySelectorAll('[data-edit-appointment]').forEach(row => {
        row.addEventListener('click', () => window.openAppointmentEditor(row.getAttribute('data-edit-appointment')));
    });

    // Sonraki günler (14 günden sonrası) katlı gelir
    const laterToggle = document.querySelector('[data-later-toggle]');
    const laterDays = document.getElementById('laterDays');
    if (laterToggle && laterDays) {
        laterToggle.addEventListener('click', () => {
            laterDays.hidden = false;
            laterToggle.setAttribute('aria-expanded', 'true');
            laterToggle.closest('[data-later-toggle-wrap]').remove();
        });
    }

    // Takvim işlemleri
    const calendarDays = document.getElementById('calendarDays');
    const currentMonthElement = document.getElementById('currentMonth');
    const prevMonthButton = document.getElementById('prevMonth');
    const nextMonthButton = document.getElementById('nextMonth');

    if (calendarDays && currentMonthElement && prevMonthButton && nextMonthButton) {
        window.initializeCalendar();
    }

    // Görünüm değiştirme
    window.changeView = function(view) {
        const url = new URL(window.location.href);
        url.searchParams.set('view', view);
        url.searchParams.delete('action');
        window.history.replaceState({}, '', url);
        const viewInput = document.querySelector('#addAppointmentModal input[name="view"]');
        if (viewInput) viewInput.value = view;
    };

    // Sayfa yüklendiğinde URL'deki görünümü kontrol et
    const urlParams = new URLSearchParams(window.location.search);
    const view = urlParams.get('view');
    if (view === 'calendar') {
        const calendarTab = document.getElementById('calendar-tab');
        if (calendarTab) calendarTab.click();
    } else {
        const listTab = document.getElementById('list-tab');
        if (listTab) listTab.click();
    }
};

// Randevu arama fonksiyonu
window.performAppointmentSearch = function(date) {
    const esc = window.escapeHtml;
    const searchButton = document.getElementById('searchButton');
    const searchResultsModal = document.getElementById('searchResultsModal');
    const searchResults = document.getElementById('searchResults');

    if (!searchResults) return;

    // Loading göster
    window.setLoadingState(searchButton, true);

    fetch(`process/search-appointments?date=${encodeURIComponent(date)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Modal başlığını güncelle
                const modalTitle = searchResultsModal.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.textContent = `${data.formatted_date} ${data.day_name}`;
                }

                // Arama sonuçlarını göster
                searchResults.innerHTML = '';

                if (data.appointments.length === 0) {
                    searchResults.innerHTML = `
                        <div class="empty">
                            <p class="empty-title">Bu tarihte randevu yok</p>
                            <p>${esc(data.formatted_date)} ${esc(data.day_name)} için kayıtlı seans bulunmuyor.</p>
                        </div>
                    `;
                } else {
                    const list = document.createElement('div');
                    list.className = 'list';
                    data.appointments.forEach(appointment => {
                        const item = document.createElement('div');
                        item.className = 'row-item';
                        item.innerHTML = `
                            <span class="row-time">${esc(appointment.formatted_time)}</span>
                            <div class="row-main">
                                <p class="row-title"><span>${esc(appointment.client_name)}</span></p>
                                <p class="row-meta tnum">${esc(window.formatPhoneDisplay(appointment.client_phone))}${appointment.notes ? ' · ' + esc(appointment.notes) : ''}</p>
                            </div>
                            <div class="row-trail"></div>
                            <div class="row-actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-edit-appointment="${esc(appointment.id)}">Düzenle</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-delete-appointment="${esc(appointment.id)}">Sil</button>
                            </div>
                        `;
                        list.appendChild(item);
                    });
                    searchResults.appendChild(list);

                    list.querySelectorAll('[data-edit-appointment]').forEach(btn => {
                        btn.addEventListener('click', () => window.editAppointmentFromSearch(btn.getAttribute('data-edit-appointment')));
                    });
                    list.querySelectorAll('[data-delete-appointment]').forEach(btn => {
                        btn.addEventListener('click', () => window.deleteAppointmentFromSearch(btn.getAttribute('data-delete-appointment')));
                    });
                }

                // Modal göster
                if (searchResultsModal && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(searchResultsModal).show();
                }
            } else {
                window.showToastMessage(data.error || 'Arama sırasında bir hata oluştu.', 'error');
            }
        })
        .catch(error => {
            console.error('Arama hatası:', error);
            window.showToastMessage('Arama sırasında bir hata oluştu.', 'error');
        })
        .finally(() => {
            window.setLoadingState(searchButton, false);
        });
};

// Bir modalı, açık olan arama modalı kapandıktan sonra aç
function openModalAfterSearch(modalId, fallback) {
    const searchEl = document.getElementById('searchResultsModal');
    const searchModal = searchEl ? bootstrap.Modal.getInstance(searchEl) : null;
    const open = () => {
        const target = document.getElementById(modalId);
        if (target && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(target).show();
        } else if (typeof fallback === 'function') {
            fallback();
        }
    };
    if (searchModal && searchEl.classList.contains('show')) {
        searchEl.addEventListener('hidden.bs.modal', open, { once: true });
        searchModal.hide();
    } else {
        open();
    }
}

// Arama modalı kapandıktan sonra bir işlem çalıştır
function afterSearchClosed(fn) {
    const searchEl = document.getElementById('searchResultsModal');
    const searchModal = searchEl ? bootstrap.Modal.getInstance(searchEl) : null;
    if (searchModal && searchEl.classList.contains('show')) {
        searchEl.addEventListener('hidden.bs.modal', fn, { once: true });
        searchModal.hide();
    } else {
        fn();
    }
}

// Arama modalından randevu düzenleme
window.editAppointmentFromSearch = function(appointmentId) {
    afterSearchClosed(() => window.openAppointmentEditor(appointmentId));
};

// Arama modalından randevu silme
window.deleteAppointmentFromSearch = function(appointmentId) {
    afterSearchClosed(() => window.openAppointmentDelete(appointmentId));
};

// Takvim başlatma fonksiyonu
window.initializeCalendar = function() {
    const esc = window.escapeHtml;
    const calendarDays = document.getElementById('calendarDays');
    const currentMonthElement = document.getElementById('currentMonth');
    const prevMonthButton = document.getElementById('prevMonth');
    const nextMonthButton = document.getElementById('nextMonth');
    const dayDetail = document.getElementById('calendarDayDetail');

    const monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
                        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    const dayNames = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    const today = new Date();
    let currentMonth = today.getMonth();
    let currentYear = today.getFullYear();
    let selectedKey = toKey(today);

    function toKey(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function appointmentsOn(key) {
        return (window.appointments || [])
            .filter(apt => apt.appointment_date === key)
            .sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
    }

    function getAppointmentStatus(appointment) {
        const aptDateTime = new Date(appointment.appointment_date + 'T' + appointment.appointment_time);
        const now = new Date();

        if (aptDateTime < now) return 'past';
        if (aptDateTime.toDateString() === now.toDateString()) return 'today';
        return 'future';
    }

    function showAppointmentDetails(appointment) {
        window.openAppointmentEditor(appointment.id);
    }

    function openAddFor(dateStr) {
        const dateInput = document.getElementById('date');
        if (dateInput) dateInput.value = dateStr;
        const modal = document.getElementById('addAppointmentModal');
        if (modal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    }

    // Seçilen günün randevularını takvimin altında listele (telefonda asıl görünüm)
    function renderDayDetail(key) {
        if (!dayDetail) return;
        const [y, m, d] = key.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        const items = appointmentsOn(key);
        const isPastDay = key < toKey(new Date());

        let html = `<div class="list-day">${d} ${monthNames[m - 1]} ${dayNames[date.getDay()]}<span>${items.length ? items.length + ' seans' : ''}</span></div>`;
        if (items.length === 0) {
            html += `<div class="empty">
                <p class="empty-title">Bu günde seans yok</p>
                ${isPastDay ? '<p>Geçmiş bir gün seçtiniz.</p>' : `<p>Bu güne randevu eklemek için aşağıdaki düğmeyi kullanın.</p><button type="button" class="btn btn-primary" data-add-on="${key}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Bu güne randevu ekle</button>`}
            </div>`;
        } else {
            html += '<div class="list">';
            items.forEach(apt => {
                const past = getAppointmentStatus(apt) === 'past';
                const editable = window.canEditAppointment(apt);
                const cancelled = apt.status === 'iptal';
                const tag = editable ? 'button type="button"' : 'div';
                const closeTag = editable ? 'button' : 'div';
                html += `<${tag} class="row-item${past ? ' is-past' : ''}${cancelled ? ' is-cancelled' : ''}" ${editable ? `data-edit-id="${esc(apt.id)}"` : ''}>
                    <span class="row-time">${esc(apt.formatted_time)}</span>
                    <span class="row-main">
                        <span class="row-title"><span>${esc(apt.client_name)}</span></span>
                        <span class="row-meta d-block">${(() => { const m = window.appointmentStatusMark(apt); return m ? m + ' · ' : ''; })()}<span class="tnum">${esc(window.formatPhoneDisplay(apt.client_phone || ''))}</span></span>
                    </span>
                    <span class="row-trail">${editable ? '<i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i>' : ''}</span>
                </${closeTag}>`;
            });
            html += '</div>';
            if (!isPastDay) {
                html += `<div class="mt-3"><button type="button" class="btn btn-secondary btn-block" data-add-on="${key}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Bu güne randevu ekle</button></div>`;
            }
        }
        dayDetail.innerHTML = html;

        dayDetail.querySelectorAll('[data-edit-id]').forEach(el => {
            el.addEventListener('click', () => {
                const apt = items.find(a => String(a.id) === el.getAttribute('data-edit-id'));
                if (apt) showAppointmentDetails(apt);
            });
        });
        dayDetail.querySelectorAll('[data-add-on]').forEach(el => {
            el.addEventListener('click', () => openAddFor(el.getAttribute('data-add-on')));
        });
    }

    function updateCalendar() {
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const startingDay = firstDay.getDay() || 7; // Pazartesi = 1, Pazar = 7
        const monthLength = lastDay.getDate();

        currentMonthElement.textContent = `${monthNames[currentMonth]} ${currentYear}`;
        calendarDays.innerHTML = '';

        // Önceki ayın günlerini ekle
        const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
        for (let i = startingDay - 1; i > 0; i--) {
            calendarDays.appendChild(createDayElement(prevMonthLastDay - i + 1, 'other-month'));
        }

        // Mevcut ayın günlerini ekle
        for (let i = 1; i <= monthLength; i++) {
            const dayDate = new Date(currentYear, currentMonth, i);
            const key = toKey(dayDate);
            const isToday = key === toKey(new Date());
            const dayElement = createDayElement(i, isToday ? 'today' : '', key);
            if (key === selectedKey) dayElement.classList.add('is-selected');

            const dayAppointments = appointmentsOn(key);
            if (dayAppointments.length > 0) {
                dayElement.classList.add('has-appointments');
                const dots = document.createElement('span');
                dots.className = 'apt-dots';
                dayAppointments.forEach(apt => {
                    const aptElement = document.createElement('span');
                    aptElement.className = `appointment-item ${getAppointmentStatus(apt)}`;
                    aptElement.textContent = `${apt.formatted_time} ${apt.client_name}`;
                    aptElement.title = `${apt.formatted_time} · ${apt.client_name}`;
                    aptElement.addEventListener('click', (e) => {
                        // Masaüstünde doğrudan randevuyu aç
                        if (window.matchMedia('(min-width: 992px)').matches && window.canEditAppointment(apt)) {
                            e.stopPropagation();
                            showAppointmentDetails(apt);
                        }
                    });
                    dots.appendChild(aptElement);
                });
                dayElement.appendChild(dots);
                dayElement.setAttribute('aria-label', `${i} ${monthNames[currentMonth]}, ${dayAppointments.length} seans`);
            } else {
                dayElement.setAttribute('aria-label', `${i} ${monthNames[currentMonth]}, seans yok`);
            }

            calendarDays.appendChild(dayElement);
        }

        // Sonraki ayın günlerini ekle
        const remainingDays = (7 - ((startingDay - 1 + monthLength) % 7)) % 7;
        for (let i = 1; i <= remainingDays; i++) {
            calendarDays.appendChild(createDayElement(i, 'other-month'));
        }

        renderDayDetail(selectedKey);
    }

    function createDayElement(day, className, key) {
        const isOther = className.includes('other-month');
        const el = document.createElement(isOther ? 'div' : 'div');
        el.className = `calendar-day ${className}`;

        const dayNumber = document.createElement('span');
        dayNumber.className = 'calendar-day-number';
        dayNumber.textContent = day;
        el.appendChild(dayNumber);

        if (!isOther) {
            el.setAttribute('role', 'button');
            el.setAttribute('tabindex', '0');
            el.dataset.date = key;

            const select = () => {
                selectedKey = key;
                calendarDays.querySelectorAll('.calendar-day.is-selected').forEach(d => d.classList.remove('is-selected'));
                el.classList.add('is-selected');
                renderDayDetail(key);
            };
            el.addEventListener('click', select);
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    select();
                }
            });

            // Masaüstü: hücrede hızlı ekleme düğmesi
            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'btn btn-sm btn-secondary add-appointment-btn';
            addButton.innerHTML = '<i class="bi bi-plus-lg" aria-hidden="true"></i>';
            addButton.setAttribute('aria-label', `${day} ${monthNames[currentMonth]} için randevu ekle`);
            addButton.addEventListener('click', (e) => {
                e.stopPropagation();
                openAddFor(key);
            });
            el.appendChild(addButton);
        }

        return el;
    }

    prevMonthButton.addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        selectedKey = toKey(new Date(currentYear, currentMonth, 1));
        if (currentMonth === today.getMonth() && currentYear === today.getFullYear()) selectedKey = toKey(today);
        updateCalendar();
    });

    nextMonthButton.addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        selectedKey = toKey(new Date(currentYear, currentMonth, 1));
        if (currentMonth === today.getMonth() && currentYear === today.getFullYear()) selectedKey = toKey(today);
        updateCalendar();
    });

    // Takvimi başlat
    updateCalendar();
};

// Arama modalından client düzenleme
window.editClientFromSearch = function(clientId) {
    openModalAfterSearch('editClientModal' + clientId, () => {
        window.location.href = 'client-details?id=' + encodeURIComponent(clientId);
    });
};

// Arama modalından client silme
window.deleteClientFromSearch = function(clientId, clientName) {
    openModalAfterSearch('deleteClientModal' + clientId, () => {
        // Modal bulunamadıysa basit confirm kullan
        if (confirm(`${clientName} adlı danışanı silmek istediğinizden emin misiniz?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process/delete-client';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'client_id';
            input.value = clientId;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
};
