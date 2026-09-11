/**
 * Gaggle NFT — Whitelist Application Form JavaScript
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('wl-application-form');
    if (!form) return;

    const steps = document.querySelectorAll('.form-step');
    const stepItems = document.querySelectorAll('.step-item');
    const connectors = document.querySelectorAll('.step-connector');
    let currentStep = 0;
    let isSubmitting = false;

    // ---- Step Navigation ----
    function showStep(index) {
        steps.forEach((step, i) => {
            step.classList.toggle('active', i === index);
        });

        stepItems.forEach((item, i) => {
            item.classList.remove('active', 'completed');
            if (i < index) item.classList.add('completed');
            if (i === index) item.classList.add('active');
        });

        connectors.forEach((conn, i) => {
            conn.style.background = i < index ? 'var(--gaggle-green-dark)' : 'var(--dark-border)';
        });

        currentStep = index;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Next step buttons
    document.querySelectorAll('.btn-next-step').forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentStep === 0 && !validateTasks()) return;
            if (currentStep === 1 && !validateFields()) return;
            if (currentStep < steps.length - 1) {
                showStep(currentStep + 1);
            }
        });
    });

    // Previous step buttons
    document.querySelectorAll('.btn-prev-step').forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentStep > 0) {
                showStep(currentStep - 1);
            }
        });
    });

    // ---- Task Cards ----
    document.querySelectorAll('.task-card').forEach(card => {
        card.addEventListener('click', (e) => {
            // Don't toggle when clicking the "Go" link
            if (e.target.closest('.task-link')) return;

            const checkbox = card.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                card.classList.toggle('checked', checkbox.checked);
            }
        });
    });

    // ---- Validate Tasks ----
    function validateTasks() {
        const requiredTasks = document.querySelectorAll('.task-card[data-required="1"]');
        let allCompleted = true;

        requiredTasks.forEach(card => {
            const checkbox = card.querySelector('input[type="checkbox"]');
            if (!checkbox || !checkbox.checked) {
                allCompleted = false;
                card.style.borderColor = 'var(--gaggle-crimson-light)';
                setTimeout(() => {
                    card.style.borderColor = '';
                }, 2000);
            }
        });

        if (!allCompleted) {
            showFormError('Please complete all required tasks before proceeding.');
            return false;
        }

        clearFormError();
        return true;
    }

    // ---- Validate Form Fields ----
    function validateFields() {
        clearFieldErrors();
        let isValid = true;
        const fields = form.querySelectorAll('.form-step.active .form-control-custom[data-required="1"]');

        fields.forEach(field => {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required.');
                isValid = false;
            }
        });

        // Wallet validation is only a UX pre-check; the server remains authoritative.
        const walletField = document.getElementById('wallet_address');
        if (walletField && walletField.value.trim()) {
            const wallet = walletField.value.trim();
            const chain = (form.dataset.blockchain || 'ethereum').toLowerCase();
            let valid = /^0x[0-9a-fA-F]{40}$/.test(wallet);
            let message = 'Please enter a valid Ethereum wallet address (0x...).';
            if (chain === 'solana' || chain === 'sol') {
                valid = /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(wallet);
                message = 'Please enter a valid Solana wallet address.';
            } else if (chain === 'bitcoin' || chain === 'btc') {
                valid = /^(1|3)[1-9A-HJ-NP-Za-km-z]{25,34}$/.test(wallet) || /^bc1[a-zA-HJ-NP-Z0-9]{25,87}$/.test(wallet);
                message = 'Please enter a valid Bitcoin wallet address.';
            }
            if (!valid) {
                showFieldError(walletField, message);
                isValid = false;
            }
        }

        // Email validation
        const emailField = document.getElementById('email');
        if (emailField && emailField.value.trim() && emailField.dataset.required === '1') {
            if (!emailField.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                showFieldError(emailField, 'Please enter a valid email address.');
                isValid = false;
            }
        }

        if (!isValid) {
            showFormError('Please fix the highlighted fields.');
        } else {
            clearFormError();
        }

        return isValid;
    }

    // ---- Form Submission ----
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (isSubmitting) return;

        // Final validation
        if (!validateTasks() || !validateFields()) {
            showStep(1); // Go back to fields step
            return;
        }

        isSubmitting = true;
        const submitBtn = form.querySelector('.btn-submit');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        submitBtn.disabled = true;

        try {
            const formData = new FormData(form);
            
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                // Redirect to success page
                if (result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    window.location.href = 'success.php?id=' + encodeURIComponent(result.application_id);
                }
            } else {
                // Show errors
                if (result.errors && Object.keys(result.errors).length > 0) {
                    clearFieldErrors();
                    for (const [field, message] of Object.entries(result.errors)) {
                        const input = document.getElementById(field);
                        if (input) {
                            showFieldError(input, message);
                        }
                    }
                    showStep(1); // Go to fields step
                }
                showFormError(result.message || 'Submission failed. Please try again.');

                // Reset Turnstile if present
                if (typeof turnstile !== 'undefined') {
                    turnstile.reset();
                }

                isSubmitting = false;
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Submission error:', error);
            showFormError('A network error occurred. Please try again.');
            isSubmitting = false;
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;

            if (typeof turnstile !== 'undefined') {
                turnstile.reset();
            }
        }
    });

    // ---- Error Helpers ----
    function showFormError(message) {
        let alert = document.getElementById('form-error-alert');
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'form-error-alert';
            alert.className = 'alert alert-error';
            form.insertBefore(alert, form.firstChild);
        }
        alert.textContent = message;
        alert.style.display = 'block';
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearFormError() {
        const alert = document.getElementById('form-error-alert');
        if (alert) alert.style.display = 'none';
    }

    function showFieldError(input, message) {
        input.classList.add('error');
        let errorEl = input.parentNode.querySelector('.form-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            input.parentNode.appendChild(errorEl);
        }
        errorEl.textContent = message;
    }

    function clearFieldErrors() {
        form.querySelectorAll('.form-control-custom.error').forEach(el => el.classList.remove('error'));
        form.querySelectorAll('.form-error').forEach(el => el.remove());
    }

    // Initialize first step
    showStep(0);
});
