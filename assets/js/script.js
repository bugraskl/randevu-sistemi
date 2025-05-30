document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const overlay = document.querySelector('.sidebar-overlay');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', toggleSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    // Mobil görünümde sidebar'ı varsayılan olarak kapalı yap
    if (window.innerWidth <= 768) {
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
    }

    // Pencere boyutu değiştiğinde kontrol et
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        } else {
            if (sidebar) sidebar.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        }
    });

    // Tema değiştirme işlemleri
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        const themeIcon = themeToggle.querySelector('i');
        
        // Kaydedilmiş temayı kontrol et ve ikonu güncelle
        if (document.body.classList.contains('dark')) {
            themeIcon.classList.remove('bi-moon-fill');
            themeIcon.classList.add('bi-sun-fill');
        }

        // Tema değiştirme butonu tıklama olayı
        themeToggle.addEventListener('click', function() {
            if (document.body.classList.contains('dark')) {
                document.body.classList.remove('dark');
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill');
                document.cookie = "theme=light; path=/; max-age=31536000";
            } else {
                document.body.classList.add('dark');
                themeIcon.classList.remove('bi-moon-fill');
                themeIcon.classList.add('bi-sun-fill');
                document.cookie = "theme=dark; path=/; max-age=31536000";
            }
        });
    }

    // Arama fonksiyonu
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const tableRows = document.querySelectorAll('tbody tr');

    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        tableRows.forEach(row => {
            const name = row.cells[0].textContent.toLowerCase();
            const phone = row.cells[1] ? row.cells[1].textContent.toLowerCase() : '';
            
            if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Arama butonuna tıklandığında
    if (searchButton) {
        searchButton.addEventListener('click', performSearch);
    }

    // Enter tuşuna basıldığında
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });

        // Input değiştiğinde anlık arama
        searchInput.addEventListener('input', performSearch);
    }

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
        // Diğer sayfalar için case'ler eklenebilir
    }
});

// Toast mesajları için global fonksiyon
window.showToastMessage = function(message, type) {
    if (window.toast && typeof window.toast.show === 'function') {
        window.toast.show(message, type);
    }
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
window.performClientSearch = function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const searchResultsModal = document.getElementById('searchResultsModal');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    const searchTerm = searchInput.value.trim();
    if (searchTerm.length < 2) {
        window.showToastMessage('Lütfen en az 2 karakter giriniz.', 'warning');
        return;
    }

    window.setLoadingState(searchButton, true);

    fetch(`process/search-clients?term=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            searchResults.innerHTML = '';
            if (data.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item">Danışan bulunamadı.</div>';
            } else {
                data.forEach(client => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item';
                    item.innerHTML = `
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">${client.name}</h5>
                                <p class="mb-1">
                                    <span class="badge bg-primary me-2">${client.phone}</span>
                                    ${client.email ? `<span class="badge bg-info">${client.email}</span>` : ''}
                                </p>
                            </div>
                            <div>
                                <a href="client-details?id=${client.id}" class="btn btn-sm btn-dark">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editClientModal${client.id}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteClientModal${client.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    searchResults.appendChild(item);
                });
            }
            
            // Modal göster
            if (searchResultsModal && typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(searchResultsModal);
                modal.show();
            }
        })
        .catch(error => {
            console.error('Arama hatası:', error);
            searchResults.innerHTML = '<div class="list-group-item text-danger">Arama sırasında bir hata oluştu.</div>';
            if (searchResultsModal && typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(searchResultsModal);
                modal.show();
            }
        })
        .finally(() => {
            window.setLoadingState(searchButton, false);
        });
};

// Client sayfası için event listener'ları
window.initializeClientPage = function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    
    if (searchButton) {
        searchButton.addEventListener('click', window.performClientSearch);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                window.performClientSearch();
            }
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
                window.location.href = `?date=${selectedDate}&view=${new URLSearchParams(window.location.search).get('view') || 'list'}`;
            }
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
        window.history.pushState({}, '', url);
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

// Takvim başlatma fonksiyonu
window.initializeCalendar = function() {
    const calendarDays = document.getElementById('calendarDays');
    const currentMonthElement = document.getElementById('currentMonth');
    const prevMonthButton = document.getElementById('prevMonth');
    const nextMonthButton = document.getElementById('nextMonth');
    
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    function updateCalendar() {
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const startingDay = firstDay.getDay() || 7; // Pazartesi = 1, Pazar = 7
        const monthLength = lastDay.getDate();
        
        // Ay adını güncelle
        const monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 
                          'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        currentMonthElement.textContent = `${monthNames[currentMonth]} ${currentYear}`;
        
        // Takvim günlerini temizle
        calendarDays.innerHTML = '';
        
        // Önceki ayın günlerini ekle
        const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
        for (let i = startingDay - 1; i > 0; i--) {
            const dayElement = createDayElement(prevMonthLastDay - i + 1, 'other-month');
            calendarDays.appendChild(dayElement);
        }
        
        // Mevcut ayın günlerini ekle
        for (let i = 1; i <= monthLength; i++) {
            const dayDate = new Date(currentYear, currentMonth, i);
            const isToday = dayDate.toDateString() === new Date().toDateString();
            const dayElement = createDayElement(i, isToday ? 'today' : '');
            
            // O güne ait randevuları ekle
            if (window.appointments) {
                const dayAppointments = window.appointments.filter(apt => {
                    const aptDate = new Date(apt.appointment_date);
                    return aptDate.getDate() === i && 
                           aptDate.getMonth() === currentMonth && 
                           aptDate.getFullYear() === currentYear;
                });
                
                if (dayAppointments.length > 0) {
                    dayElement.classList.add('has-appointments');
                    dayAppointments.forEach(apt => {
                        const aptElement = document.createElement('div');
                        aptElement.className = `appointment-item ${getAppointmentStatus(apt)}`;
                        aptElement.textContent = `${apt.formatted_time} - ${apt.client_name}`;
                        aptElement.onclick = () => showAppointmentDetails(apt);
                        dayElement.appendChild(aptElement);
                    });
                }
            }
            
            calendarDays.appendChild(dayElement);
        }
        
        // Sonraki ayın günlerini ekle
        const remainingDays = 42 - (startingDay - 1 + monthLength); // 6 satır için 42 gün
        for (let i = 1; i <= remainingDays; i++) {
            const dayElement = createDayElement(i, 'other-month');
            calendarDays.appendChild(dayElement);
        }
    }
    
    function createDayElement(day, className) {
        const div = document.createElement('div');
        div.className = `calendar-day ${className}`;
        
        // Gün numarası
        const dayNumber = document.createElement('div');
        dayNumber.className = 'calendar-day-number';
        dayNumber.textContent = day;
        div.appendChild(dayNumber);
        
        // Boş günler için randevu ekleme butonu
        if (!className.includes('other-month')) {
            const addButton = document.createElement('button');
            addButton.className = 'btn btn-sm btn-outline-secondary add-appointment-btn';
            addButton.innerHTML = '<i class="bi bi-plus"></i>';
            addButton.style.position = 'absolute';
            addButton.style.bottom = '5px';
            addButton.style.right = '5px';
            addButton.style.padding = '2px 6px';
            addButton.style.fontSize = '0.8rem';
            
            // Tarih formatını oluştur
            const year = currentYear;
            const month = (currentMonth + 1).toString().padStart(2, '0');
            const dayStr = day.toString().padStart(2, '0');
            const dateStr = `${year}-${month}-${dayStr}`;
            
            addButton.onclick = (e) => {
                e.stopPropagation();
                const dateInput = document.getElementById('date');
                if (dateInput) dateInput.value = dateStr;
                const modal = document.getElementById('addAppointmentModal');
                if (modal && typeof bootstrap !== 'undefined') {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }
            };
            
            div.appendChild(addButton);
        }
        
        return div;
    }
    
    function getAppointmentStatus(appointment) {
        const aptDateTime = new Date(appointment.appointment_date + 'T' + appointment.appointment_time);
        const now = new Date();
        
        if (aptDateTime < now) return 'past';
        if (aptDateTime.toDateString() === now.toDateString()) return 'today';
        return 'future';
    }
    
    function showAppointmentDetails(appointment) {
        const modal = document.getElementById('editAppointmentModal' + appointment.id);
        if (modal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }
    
    prevMonthButton.addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        updateCalendar();
    });
    
    nextMonthButton.addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        updateCalendar();
    });
    
    // Takvimi başlat
    updateCalendar();
}; 