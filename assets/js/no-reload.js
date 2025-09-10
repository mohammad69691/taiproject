// No-Reload Action Handler
// Prevents page reloads from re-executing actions

class NoReloadHandler {
    constructor() {
        this.processedActions = new Set();
        this.init();
    }

    init() {
        // Handle form submissions
        this.handleFormSubmissions();
        
        // Handle button clicks that trigger actions
        this.handleActionButtons();
        
        // Store processed actions in sessionStorage
        this.loadProcessedActions();
    }

    handleFormSubmissions() {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const action = form.querySelector('input[name="action"]')?.value;
            
            // Allow certain actions to be repeated (like password resets)
            const allowedRepeatActions = ['reset_password', 'change_password', 'update_password'];
            
            if (action && this.isActionProcessed(action) && !allowedRepeatActions.includes(action)) {
                e.preventDefault();
                this.showMessage('Tämä toiminto on jo suoritettu.', 'warning');
                return false;
            }
            
            if (action && !allowedRepeatActions.includes(action)) {
                this.markActionAsProcessed(action);
            }
            
            if (action) {
                this.showLoadingState(form);
                
                // Add a timestamp to prevent browser caching
                const timestamp = Date.now();
                const timestampInput = document.createElement('input');
                timestampInput.type = 'hidden';
                timestampInput.name = 'timestamp';
                timestampInput.value = timestamp;
                form.appendChild(timestampInput);
            }
        });
    }

    handleActionButtons() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (!button) return;
            
            const action = button.dataset.action;
            
            // Allow certain actions to be repeated (like password resets)
            const allowedRepeatActions = ['reset_password', 'change_password', 'update_password'];
            
            if (action && this.isActionProcessed(action) && !allowedRepeatActions.includes(action)) {
                e.preventDefault();
                this.showMessage('Tämä toiminto on jo suoritettu.', 'warning');
                return false;
            }
            
            if (action && !allowedRepeatActions.includes(action)) {
                this.markActionAsProcessed(action);
            }
        });
    }

    isActionProcessed(action) {
        return this.processedActions.has(action);
    }

    markActionAsProcessed(action) {
        this.processedActions.add(action);
        this.saveProcessedActions();
    }

    loadProcessedActions() {
        try {
            const stored = sessionStorage.getItem('processedActions');
            if (stored) {
                this.processedActions = new Set(JSON.parse(stored));
            }
        } catch (e) {
            console.warn('Could not load processed actions:', e);
        }
    }

    saveProcessedActions() {
        try {
            sessionStorage.setItem('processedActions', JSON.stringify([...this.processedActions]));
        } catch (e) {
            console.warn('Could not save processed actions:', e);
        }
    }

    showLoadingState(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Käsitellään...';
        }
    }

    showMessage(message, type = 'info') {
        // Create a temporary message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        messageDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(messageDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Clear processed actions (useful for testing or manual reset)
    clearProcessedActions() {
        this.processedActions.clear();
        sessionStorage.removeItem('processedActions');
    }

    // Clear specific action (useful for allowing retry of specific actions)
    clearSpecificAction(action) {
        this.processedActions.delete(action);
        this.saveProcessedActions();
    }

    // Clear password-related actions (called after successful password operations)
    clearPasswordActions() {
        const passwordActions = ['reset_password', 'change_password', 'update_password'];
        passwordActions.forEach(action => {
            this.processedActions.delete(action);
        });
        this.saveProcessedActions();
    }

    // Reset form states
    resetFormStates() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton && submitButton.disabled) {
                submitButton.disabled = false;
                submitButton.innerHTML = submitButton.dataset.originalText || 'Tallenna';
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.noReloadHandler = new NoReloadHandler();
    
    // Reset form states on page load
    window.noReloadHandler.resetFormStates();
    
    // Clear password actions if we're on a success page
    if (window.location.search.includes('password_reset=success') || 
        window.location.search.includes('password_changed=success')) {
        window.noReloadHandler.clearPasswordActions();
    }
});

// Global functions for use in PHP pages
window.clearPasswordActions = function() {
    if (window.noReloadHandler) {
        window.noReloadHandler.clearPasswordActions();
    }
};

window.clearSpecificAction = function(action) {
    if (window.noReloadHandler) {
        window.noReloadHandler.clearSpecificAction(action);
    }
};

// Handle page visibility changes (when user comes back to tab)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && window.noReloadHandler) {
        window.noReloadHandler.resetFormStates();
    }
});

// Handle page navigation to prevent form resubmission
window.addEventListener('pageshow', (e) => {
    // If the page was loaded from cache (back/forward button), clear form states
    if (e.persisted) {
        if (window.noReloadHandler) {
            window.noReloadHandler.resetFormStates();
            // Clear all processed actions when coming from cache
            window.noReloadHandler.clearProcessedActions();
        }
    }
});

// Handle beforeunload to warn about unsaved changes
window.addEventListener('beforeunload', (e) => {
    const forms = document.querySelectorAll('form');
    let hasUnsavedChanges = false;
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input:not([type="hidden"]):not([readonly]):not([disabled]), textarea:not([readonly]):not([disabled])');
        inputs.forEach(input => {
            if (input.value && input.value.trim() !== '') {
                hasUnsavedChanges = true;
            }
        });
    });
    
    if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = 'Sinulla on tallentamattomia muutoksia. Haluatko varmasti poistua sivulta?';
        return e.returnValue;
    }
});

// Prevent form resubmission by replacing history state after form submission
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on a page that was reached via POST
    if (window.performance && window.performance.navigation) {
        if (window.performance.navigation.type === 1) { // Reload
            // Clear any processed actions on reload
            if (window.noReloadHandler) {
                window.noReloadHandler.clearProcessedActions();
            }
        }
    }
    
    // Replace current history state to prevent back button issues
    if (window.history && window.history.replaceState) {
        const currentUrl = new URL(window.location);
        // Remove any form-related parameters
        currentUrl.searchParams.delete('timestamp');
        currentUrl.searchParams.delete('form_submitted');
        
        window.history.replaceState(null, '', currentUrl.toString());
    }
});
