// Kullanıcı Yönetimi JavaScript İşlemleri

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    let searchTimeout;

    // Arama işlemleri
    if (searchInput && searchButton) {
        // Gerçek zamanlı arama
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 500);
        });

        // Arama butonu tıklama
        searchButton.addEventListener('click', function() {
            performSearch(searchInput.value);
        });

        // Enter tuşu ile arama
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch(this.value);
            }
        });
    }

    // Modal işlemleri için iyileştirilmiş loading yönetimi
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        // Modal kapanma durumunda tüm durumları sıfırla
        modal.addEventListener('hidden.bs.modal', function() {
            resetModalState(this);
        });
        
        // Modal açılma durumunda (sadece add modal için form temizleme)
        if (modal.id === 'addUserModal') {
            modal.addEventListener('show.bs.modal', function() {
                const form = this.querySelector('form');
                if (form) {
                    form.reset();
                    resetFormValidation(form);
                }
            });
        }
    });

    // Form gönderme işlemleri - geliştirilmiş versiyon
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn?.querySelector('.spinner-border');
            
            // Eğer form geçerli değilse loading'i başlatma
            if (!form.checkValidity()) {
                if (spinner && submitBtn) {
                    spinner.classList.add('d-none');
                    submitBtn.disabled = false;
                }
                return;
            }
            
            // Loading state'i başlat
            if (spinner && submitBtn) {
                startLoadingState(submitBtn, spinner);
                
                // Güvenlik için timeout ekle
                setTimeout(() => {
                    stopLoadingState(submitBtn, spinner);
                }, 20000); // 20 saniye timeout
            }
        });
    });

    // Sayfa yüklendiğinde tüm loading state'leri temizle
    cleanAllLoadingStates();
});

// Loading state başlatma fonksiyonu
function startLoadingState(button, spinner) {
    if (spinner && button) {
        spinner.classList.remove('d-none');
        button.disabled = true;
        button.setAttribute('data-loading', 'true');
    }
}

// Loading state'i durdurma fonksiyonu
function stopLoadingState(button, spinner) {
    if (spinner && button) {
        spinner.classList.add('d-none');
        button.disabled = false;
        button.removeAttribute('data-loading');
    }
}

// Modal durumunu sıfırlama fonksiyonu
function resetModalState(modal) {
    const form = modal.querySelector('form');
    if (form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const spinner = submitBtn?.querySelector('.spinner-border');
        
        // Loading state'i temizle
        stopLoadingState(submitBtn, spinner);
        
        // Form validation durumunu temizle
        resetFormValidation(form);
    }
}

// Form validation durumunu sıfırlama fonksiyonu
function resetFormValidation(form) {
    form.classList.remove('was-validated');
    
    // Tüm hatalı alanları temizle
    const invalidInputs = form.querySelectorAll('.is-invalid');
    invalidInputs.forEach(input => {
        input.classList.remove('is-invalid');
    });
    
    // Tüm geçerli alanları temizle
    const validInputs = form.querySelectorAll('.is-valid');
    validInputs.forEach(input => {
        input.classList.remove('is-valid');
    });
}

// Tüm loading state'leri temizleme fonksiyonu
function cleanAllLoadingStates() {
    const allSpinners = document.querySelectorAll('.spinner-border');
    const allSubmitBtns = document.querySelectorAll('button[type="submit"]');
    
    allSpinners.forEach(spinner => {
        spinner.classList.add('d-none');
    });
    
    allSubmitBtns.forEach(btn => {
        btn.disabled = false;
        btn.removeAttribute('data-loading');
    });
}

// Sayfa görünür olduğunda loading state'leri temizle
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        cleanAllLoadingStates();
    }
});

// Sayfa geri geldiğinde loading state'leri temizle (browser back/forward)
window.addEventListener('pageshow', function(event) {
    cleanAllLoadingStates();
});

function performSearch(searchTerm) {
    const tableBody = document.querySelector('tbody');
    const noDataMessage = document.querySelector('.text-center.py-5');
    
    if (!tableBody) return;

    // Loading durumu göster
    showSearchLoading(tableBody);

    fetch(`process/search-users.php?search=${encodeURIComponent(searchTerm)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Arama işlemi başarısız');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            displaySearchResults(data.users, tableBody);
        })
        .catch(error => {
            console.error('Arama hatası:', error);
            if (window.toast) {
                window.toast.show('Arama yapılırken bir hata oluştu: ' + error.message, 'error');
            }
            hideSearchLoading(tableBody);
        });
}

function showSearchLoading(tableBody) {
    tableBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Aranıyor...</span>
                </div>
                <p class="mt-2 mb-0 text-muted">Kullanıcılar aranıyor...</p>
            </td>
        </tr>
    `;
}

function hideSearchLoading(tableBody) {
    // Sayfa yenilenmesini tetikle veya önceki sonuçları geri getir
    window.location.reload();
}

function displaySearchResults(users, tableBody) {
    if (users.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="bi bi-search display-4 text-muted"></i>
                    <p class="mt-3 text-muted">Arama kriterlerinize uygun kullanıcı bulunamadı.</p>
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = '';
    
    users.forEach(user => {
        const row = createUserRow(user);
        tableBody.appendChild(row);
    });
}

function createUserRow(user) {
    const tr = document.createElement('tr');
    
    const roleText = user.role === 'admin' ? 'Yönetici' : 'Kullanıcı';
    const roleBadgeClass = user.role === 'admin' ? 'bg-danger' : 'bg-secondary';
    
    const statusText = user.status === 'active' ? 'Aktif' : 'Pasif';
    const statusBadgeClass = user.status === 'active' ? 'bg-success' : 'bg-warning';
    
    const createdDate = new Date(user.created_at).toLocaleString('tr-TR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });

    // Mevcut kullanıcının ID'sini al (global değişken olarak tanımlanmış olmalı)
    const currentUserId = window.currentUserId || null;
    
    tr.innerHTML = `
        <td>${escapeHtml(user.name)}</td>
        <td>${escapeHtml(user.email)}</td>
        <td>
            <span class="badge ${roleBadgeClass}">
                ${roleText}
            </span>
        </td>
        <td>
            <span class="badge ${statusBadgeClass}">
                ${statusText}
            </span>
        </td>
        <td>${createdDate}</td>
        <td>
            <button type="button" class="btn btn-sm btn-primary" onclick="openEditModal(${user.id})">
                <i class="bi bi-pencil"></i>
            </button>
            ${user.id != currentUserId ? `
                <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteModal(${user.id}, '${escapeHtml(user.name)}')">
                    <i class="bi bi-trash"></i>
                </button>
            ` : ''}
        </td>
    `;
    
    return tr;
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function openEditModal(userId) {
    // Modal'ı aç (Bootstrap modal'ı)
    const modal = document.getElementById(`editUserModal${userId}`);
    if (modal) {
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }
}

function openDeleteModal(userId, userName) {
    // Modal'ı aç (Bootstrap modal'ı)
    const modal = document.getElementById(`deleteUserModal${userId}`);
    if (modal) {
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }
}

// E-posta validasyonu
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Şifre gücü kontrolü
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength += 1;
    if (password.match(/[a-z]/)) strength += 1;
    if (password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    return strength;
}

// Form validasyon yardımcı fonksiyonları
function showFieldError(field, message) {
    field.classList.add('is-invalid');
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = message;
    }
}

function hideFieldError(field) {
    field.classList.remove('is-invalid');
} 