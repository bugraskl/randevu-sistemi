class Toast {
    constructor() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${this.getTypeClass(type)} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${this.getIcon(type)} ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        this.container.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    getTypeClass(type) {
        switch(type) {
            case 'success': return 'success';
            case 'error': return 'danger';
            case 'warning': return 'warning';
            default: return 'info';
        }
    }

    getIcon(type) {
        switch(type) {
            case 'success': return '<i class="bi bi-check-circle-fill me-2"></i>';
            case 'error': return '<i class="bi bi-x-circle-fill me-2"></i>';
            case 'warning': return '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
            default: return '<i class="bi bi-info-circle-fill me-2"></i>';
        }
    }
}

// Global toast instance
window.toast = new Toast(); 