/**
 * Payment.js - Client-side payment flow for Razorpay integration
 * Handles profile save, payment initiation, Razorpay checkout, and payment verification
 */

(function() {
    'use strict';
    
    // Payment configuration (set by page)
    window.PaymentFlow = {
        csrfToken: '',
        amount: 1,
        
        /**
         * Initialize payment flow
         */
        init: function(config) {
            this.csrfToken = config.csrf || '';
            this.amount = config.amount || 1;
        },
        
        /**
         * Save profile via AJAX and then initiate payment
         */
        saveAndPay: function() {
            const form = document.getElementById('player-profile-form');
            if (!form) {
                alert('Form not found');
                return;
            }
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Show loading state
            const payBtn = document.getElementById('save-pay-btn');
            if (payBtn) {
                payBtn.disabled = true;
                payBtn.textContent = 'Processing...';
            }
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Check if save was successful by looking for success message
                if (html.includes('Player profile saved successfully') || html.includes('msg success')) {
                    // Profile saved, now initiate payment
                    this.initiatePayment();
                } else if (html.includes('msg error') || html.includes('error')) {
                    // Extract error message if possible
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const errorMsg = doc.querySelector('.msg.error');
                    const errorText = errorMsg ? errorMsg.textContent : 'Please fix the errors and try again';
                    alert('Profile save failed: ' + errorText);
                    this.resetButton();
                } else {
                    // Assume success if no error found
                    this.initiatePayment();
                }
            })
            .catch(error => {
                console.error('Error saving profile:', error);
                alert('Error saving profile. Please try again.');
                this.resetButton();
            });
        },
        
        /**
         * Initiate payment - create Razorpay order
         */
        initiatePayment: function() {
            const formData = new FormData();
            formData.append('csrf', this.csrfToken);
            formData.append('amount', this.amount);
            
            fetch('payment/initiate_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    // Open Razorpay checkout
                    this.openRazorpayCheckout(data);
                } else {
                    alert('Error: ' + (data.error || 'Failed to initiate payment'));
                    this.resetButton();
                }
            })
            .catch(error => {
                console.error('Error initiating payment:', error);
                alert('Error initiating payment. Please try again.');
                this.resetButton();
            });
        },
        
        /**
         * Open Razorpay checkout modal
         */
        openRazorpayCheckout: function(orderData) {
            const self = this;
            
            const options = {
                key: orderData.key_id,
                amount: orderData.amount,
                currency: orderData.currency || 'INR',
                name: 'Latur Badminton League',
                description: orderData.description || 'LBL Registration Payment',
                order_id: orderData.order_id,
                prefill: orderData.prefill || {},
                theme: {
                    color: '#2563eb'
                },
                handler: function(response) {
                    // Payment successful, verify signature
                    self.verifyPayment(response);
                },
                modal: {
                    ondismiss: function() {
                        // User closed the checkout modal
                        alert('Payment cancelled');
                        self.resetButton();
                    }
                }
            };
            
            // Check if Razorpay is loaded
            if (typeof Razorpay === 'undefined') {
                alert('Razorpay checkout library not loaded. Please refresh and try again.');
                this.resetButton();
                return;
            }
            
            const rzp = new Razorpay(options);
            rzp.open();
        },
        
        /**
         * Verify payment signature on server
         */
        verifyPayment: function(response) {
            const formData = new FormData();
            formData.append('csrf', this.csrfToken);
            formData.append('razorpay_order_id', response.razorpay_order_id);
            formData.append('razorpay_payment_id', response.razorpay_payment_id);
            formData.append('razorpay_signature', response.razorpay_signature);
            
            fetch('payment/verify_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    // Payment verified, redirect to return page
                    window.location.href = 'payment/return.php?payment_id=' + data.payment_id;
                } else {
                    alert('Payment verification failed: ' + (data.error || 'Unknown error'));
                    this.resetButton();
                }
            })
            .catch(error => {
                console.error('Error verifying payment:', error);
                alert('Error verifying payment. Please contact support.');
                this.resetButton();
            });
        },
        
        /**
         * Reset button to initial state
         */
        resetButton: function() {
            const payBtn = document.getElementById('save-pay-btn');
            if (payBtn) {
                payBtn.disabled = false;
                payBtn.textContent = 'Save & Pay';
            }
        }
    };
})();
