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
window.performClientSearch = function(page = 1) {
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
                    modalTitle.textContent = `"${data.search_term}" için ${data.pagination.total_records} sonuç bulundu`;
                }

                // İlk sayfa ise sonuçları temizle
                if (page === 1) {
                    searchResults.innerHTML = '';
                }
                
                if (data.clients.length === 0 && page === 1) {
                    searchResults.innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-person-x fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">Danışan bulunamadı</h5>
                            <p class="text-muted mb-0">"${data.search_term}" için sonuç bulunamadı</p>
                        </div>
                    `;
                } else {
                    data.clients.forEach(client => {
                        const item = document.createElement('div');
                        item.className = 'list-group-item';
                        item.innerHTML = `
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <h5 class="mb-0 me-3">${client.name}</h5>
                                        <span class="badge bg-info">${client.appointment_count} randevu</span>
                                    </div>
                                    <p class="mb-1">
                                        <i class="bi bi-telephone me-1"></i> ${client.phone}
                                        ${client.email ? `<span class="ms-3"><i class="bi bi-envelope me-1"></i> ${client.email}</span>` : ''}
                                    </p>
                                    ${client.address ? `<p class="mb-1 text-muted"><i class="bi bi-geo-alt me-1"></i> ${client.address}</p>` : ''}
                                    ${client.notes ? `<p class="mb-1 text-muted"><i class="bi bi-journal-text me-1"></i> ${client.notes}</p>` : ''}
                                    <small class="text-muted">Kayıt tarihi: ${client.created_at_formatted}</small>
                                </div>
                                <div class="btn-group ms-3" role="group">
                                    <a href="client-details?id=${client.id}" class="btn btn-sm btn-dark" title="Detaylar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editClientFromSearch(${client.id})" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteClientFromSearch(${client.id}, '${client.name}')" title="Sil">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        searchResults.appendChild(item);
                    });
                    
                    // Sayfalama ekle
                    if (data.pagination.total_pages > 1) {
                        const paginationDiv = document.createElement('div');
                        paginationDiv.className = 'mt-3 d-flex justify-content-between align-items-center';
                        paginationDiv.innerHTML = `
                            <div class="text-muted">
                                Sayfa ${data.pagination.current_page} / ${data.pagination.total_pages} 
                                (Toplam ${data.pagination.total_records} kayıt)
                            </div>
                            <div class="btn-group" role="group">
                                ${data.pagination.has_prev ? 
                                    `<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.performClientSearch(${data.pagination.current_page - 1})">
                                        <i class="bi bi-chevron-left"></i> Önceki
                                    </button>` : ''
                                }
                                ${data.pagination.has_next ? 
                                    `<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.performClientSearch(${data.pagination.current_page + 1})">
                                        Sonraki <i class="bi bi-chevron-right"></i>
                                    </button>` : ''
                                }
                            </div>
                        `;
                        searchResults.appendChild(paginationDiv);
                    }
                }
                
                // Modal göster (sadece ilk sayfa için)
                if (page === 1 && searchResultsModal && typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(searchResultsModal);
                    modal.show();
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
        searchButton.addEventListener('click', window.performClientSearch);
    }
    
    if (searchInput) {
        // Enter tuşu ile arama
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                window.performClientSearch();
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
                window.performClientSearch();
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

// Randevu arama fonksiyonu
window.performAppointmentSearch = function(date) {
    const searchButton = document.getElementById('searchButton');
    const searchResultsModal = document.getElementById('searchResultsModal');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchResults) return;

    // Loading göster
    window.setLoadingState(searchButton, true);
    searchButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aranıyor...';

    fetch(`process/search-appointments?date=${encodeURIComponent(date)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Modal başlığını güncelle
                const modalTitle = searchResultsModal.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.textContent = `${data.formatted_date} ${data.day_name} - Randevular`;
                }

                // Arama sonuçlarını göster
                searchResults.innerHTML = '';
                
                if (data.appointments.length === 0) {
                    searchResults.innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                            <h5 class="text-muted">Bu tarihte randevu bulunamadı</h5>
                            <p class="text-muted mb-0">${data.formatted_date} ${data.day_name}</p>
                        </div>
                    `;
                } else {
                    data.appointments.forEach(appointment => {
                        const appointmentElement = document.createElement('div');
                        appointmentElement.className = 'list-group-item';
                        appointmentElement.innerHTML = `
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <h6 class="mb-0 me-3">${appointment.formatted_time}</h6>
                                        ${appointment.status_badge}
                                    </div>
                                    <h5 class="mb-1">${appointment.client_name}</h5>
                                    <p class="mb-1">
                                        <i class="bi bi-telephone me-1"></i> ${appointment.client_phone}
                                    </p>
                                    ${appointment.notes ? `<p class="mb-1 text-muted"><i class="bi bi-journal-text me-1"></i> ${appointment.notes}</p>` : ''}
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editAppointmentFromSearch(${appointment.id})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteAppointmentFromSearch(${appointment.id}, '${appointment.formatted_date}', '${appointment.formatted_time}', '${appointment.client_name}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        searchResults.appendChild(appointmentElement);
                    });
                }
                
                // Modal göster
                if (searchResultsModal && typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(searchResultsModal);
                    modal.show();
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
            searchButton.innerHTML = '<i class="bi bi-search"></i> Ara';
        });
};

// Arama modalından randevu düzenleme
window.editAppointmentFromSearch = function(appointmentId) {
    // Mevcut modal'ı kapat
    const searchModal = bootstrap.Modal.getInstance(document.getElementById('searchResultsModal'));
    if (searchModal) {
        searchModal.hide();
    }
    
    // Düzenleme modalını aç
    setTimeout(() => {
        const editModal = document.getElementById('editAppointmentModal' + appointmentId);
        if (editModal && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(editModal);
            modal.show();
        }
    }, 300);
};

// Arama modalından randevu silme
window.deleteAppointmentFromSearch = function(appointmentId, date, time, clientName) {
    // Mevcut modal'ı kapat
    const searchModal = bootstrap.Modal.getInstance(document.getElementById('searchResultsModal'));
    if (searchModal) {
        searchModal.hide();
    }
    
    // Silme onay modalını aç
    setTimeout(() => {
        const deleteModal = document.getElementById('deleteAppointmentModal' + appointmentId);
        if (deleteModal && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(deleteModal);
            modal.show();
        }
    }, 300);
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

// Arama modalından client düzenleme
window.editClientFromSearch = function(clientId) {
    // Mevcut modal'ı kapat
    const searchModal = bootstrap.Modal.getInstance(document.getElementById('searchResultsModal'));
    if (searchModal) {
        searchModal.hide();
    }
    
    // Düzenleme modalını aç
    setTimeout(() => {
        const editModal = document.getElementById('editClientModal' + clientId);
        if (editModal && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(editModal);
            modal.show();
        } else {
            // Modal bulunamadıysa sayfayı yenile
            window.location.reload();
        }
    }, 300);
};

// Arama modalından client silme
window.deleteClientFromSearch = function(clientId, clientName) {
    // Mevcut modal'ı kapat
    const searchModal = bootstrap.Modal.getInstance(document.getElementById('searchResultsModal'));
    if (searchModal) {
        searchModal.hide();
    }
    
    // Silme onay modalını aç
    setTimeout(() => {
        const deleteModal = document.getElementById('deleteClientModal' + clientId);
        if (deleteModal && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(deleteModal);
            modal.show();
        } else {
            // Modal bulunamadıysa basit confirm kullan
            if (confirm(`${clientName} adlı danışanı silmek istediğinizden emin misiniz?`)) {
                // Form submit işlemi
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
        }
    }, 300);
}; 