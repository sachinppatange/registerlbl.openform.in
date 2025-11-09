/**
 * Payment JavaScript
 * 
 * Handles Razorpay checkout flow:
 * 1. Initiates payment by calling backend API
 * 2. Opens Razorpay Checkout modal
 * 3. Verifies payment on success
 * 4. Redirects to return page
 */

// Global configuration (set by including page)
window.LBL_PAYMENT = window.LBL_PAYMENT || {};

/**
 * Load Razorpay Checkout script dynamically
 */
function loadRazorpayScript() {
    return new Promise((resolve, reject) => {
        if (window.Razorpay) {
            resolve();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Razorpay script'));
        document.head.appendChild(script);
    });
}

/**
 * Call backend to initiate payment and create Razorpay order
 */
async function initiatePayment(amount, csrfToken) {
    const formData = new FormData();
    formData.append('amount', amount);
    formData.append('csrf', csrfToken);
    
    const response = await fetch('payment/initiate_payment.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to initiate payment');
    }
    
    return await response.json();
}

/**
 * Call backend to verify payment signature
 */
async function verifyPayment(razorpayOrderId, razorpayPaymentId, razorpaySignature, csrfToken) {
    const formData = new FormData();
    formData.append('razorpay_order_id', razorpayOrderId);
    formData.append('razorpay_payment_id', razorpayPaymentId);
    formData.append('razorpay_signature', razorpaySignature);
    formData.append('csrf', csrfToken);
    
    const response = await fetch('payment/verify_payment.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Payment verification failed');
    }
    
    return await response.json();
}

/**
 * Open Razorpay checkout modal
 */
function openRazorpayCheckout(orderData, csrfToken) {
    const options = {
        key: orderData.key_id,
        amount: orderData.amount,
        currency: orderData.currency,
        name: orderData.name || 'Latur Badminton League',
        description: orderData.description || 'Player Registration Fee',
        order_id: orderData.order_id,
        prefill: orderData.prefill || {},
        theme: {
            color: '#2563eb'
        },
        handler: async function(response) {
            // Payment successful, verify with backend
            try {
                const result = await verifyPayment(
                    response.razorpay_order_id,
                    response.razorpay_payment_id,
                    response.razorpay_signature,
                    csrfToken
                );
                
                // Redirect to success page
                window.location.href = 'payment/return.php?status=success&payment_id=' + result.payment_id;
            } catch (error) {
                console.error('Payment verification failed:', error);
                alert('Payment verification failed: ' + error.message);
                window.location.href = 'payment/return.php?status=failed';
            }
        },
        modal: {
            ondismiss: function() {
                console.log('Checkout form closed');
            }
        }
    };
    
    const rzp = new Razorpay(options);
    
    rzp.on('payment.failed', function(response) {
        console.error('Payment failed:', response.error);
        alert('Payment failed: ' + response.error.description);
        window.location.href = 'payment/return.php?status=failed';
    });
    
    rzp.open();
}

/**
 * Main payment flow
 * Called when "Save & Pay" button is clicked
 */
async function startPaymentFlow(amount, csrfToken) {
    try {
        // Show loading state
        const btn = document.getElementById('saveAndPayBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Processing...';
        }
        
        // Load Razorpay script
        await loadRazorpayScript();
        
        // Initiate payment (create order)
        const orderData = await initiatePayment(amount, csrfToken);
        
        if (!orderData.ok) {
            throw new Error(orderData.error || 'Failed to create order');
        }
        
        // Open Razorpay checkout
        openRazorpayCheckout(orderData, csrfToken);
        
    } catch (error) {
        console.error('Payment flow error:', error);
        alert('Payment initiation failed: ' + error.message);
        
        // Reset button state
        const btn = document.getElementById('saveAndPayBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save & Pay';
        }
    }
}

/**
 * Handle "Save & Pay" button click
 * First saves the profile, then initiates payment
 */
async function handleSaveAndPay(event) {
    event.preventDefault();
    
    const form = document.querySelector('form');
    const amount = window.LBL_PAYMENT.amount || 500;
    const csrfToken = window.LBL_PAYMENT.csrf || '';
    
    if (!csrfToken) {
        alert('Security token missing. Please refresh the page.');
        return;
    }
    
    // Validate form
    if (!form.checkValidity()) {
        // Trigger native form validation
        form.reportValidity();
        return;
    }
    
    // Save profile first via AJAX
    try {
        const btn = document.getElementById('saveAndPayBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving Profile...';
        }
        
        const formData = new FormData(form);
        
        const response = await fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const html = await response.text();
        
        // Parse HTML response to check for success/error messages
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const successMsg = doc.querySelector('.msg.success');
        const errorMsg = doc.querySelector('.msg.error');
        
        if (successMsg && successMsg.textContent.includes('Player profile saved successfully')) {
            // Profile saved successfully, now initiate payment
            if (btn) {
                btn.textContent = 'Initiating Payment...';
            }
            await startPaymentFlow(amount, csrfToken);
        } else if (errorMsg) {
            // Profile save failed with validation errors
            alert('Please fix the errors in the form: ' + errorMsg.textContent.trim());
            
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save & Pay';
            }
        } else if (response.ok && !errorMsg) {
            // Response OK but no explicit success message - assume success
            if (btn) {
                btn.textContent = 'Initiating Payment...';
            }
            await startPaymentFlow(amount, csrfToken);
        } else {
            throw new Error('Unable to determine profile save status');
        }
        
    } catch (error) {
        console.error('Save and pay error:', error);
        alert('Failed to save profile: ' + error.message);
        
        const btn = document.getElementById('saveAndPayBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save & Pay';
        }
    }
}

// Make functions available globally
window.startPaymentFlow = startPaymentFlow;
window.handleSaveAndPay = handleSaveAndPay;
