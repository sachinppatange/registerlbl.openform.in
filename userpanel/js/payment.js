// Lightweight payment helper for player_profile.php
// Exposes window.doSaveAndPay(options) -> Promise
// Usage:
//   doSaveAndPay({ amountRupees: '1.00', endpoint: 'player_profile.php', prefillName: 'John' })
//     .catch(err => console.error(err));

(function (global) {
  'use strict';

  async function jsonOrThrow(response) {
    const text = await response.text();
    try { return JSON.parse(text); }
    catch (e) {
      // If server returned HTML with success message, synthesize success
      if (text.indexOf('Player profile saved successfully!') !== -1) return { success: true };
      throw new Error('Unexpected server response');
    }
  }

  function openRazorpay(keyId, order, prefill) {
    return new Promise((resolve, reject) => {
      if (typeof Razorpay === 'undefined') {
        return reject(new Error('Razorpay checkout library not loaded'));
      }
      const options = {
        key: keyId,
        amount: order.amount,
        currency: order.currency || 'INR',
        name: prefill.name || '',
        description: 'Registration fee',
        order_id: order.id,
        prefill: {
          name: prefill.name || '',
          contact: prefill.contact || '',
          email: prefill.email || ''
        },
        theme: { color: '#2563eb' },
        handler: function (res) {
          // resolve with response from checkout (payment ids). Caller can POST to receipt page.
          resolve(res);
        },
        modal: {
          ondismiss: function () {
            reject(new Error('Checkout dismissed'));
          }
        }
      };
      try {
        const rzp = new Razorpay(options);
        rzp.open();
      } catch (e) {
        reject(e);
      }
    });
  }

  /**
   * doSaveAndPay
   * options: {
   *   endpoint: URL to post form to (defaults to current page),
   *   amountRupees: string or number (optional),
   *   prefillName: string (optional),
   *   prefillEmail: string (optional),
   *   prefillContact: string (optional)
   * }
   */
  async function doSaveAndPay(options = {}) {
    const endpoint = options.endpoint || window.location.href;
    const form = document.getElementById('profileForm');
    if (!form) throw new Error('Form #profileForm not found on page');

    // client-side HTML5 validation
    if (!form.reportValidity()) throw new Error('Please fill required fields');

    // First: Save profile and create order in one request (ajax=1 + start_payment=1)
    const fd = new FormData(form);
    fd.set('ajax', '1');
    // prefer explicit CSRF from form if present
    const csrfEl = form.querySelector('[name="csrf"]');
    if (csrfEl) fd.set('csrf', csrfEl.value);
    if (options.amountRupees !== undefined) fd.set('payment_amount', String(options.amountRupees));
    fd.set('start_payment', '1');

    const resp = await fetch(endpoint, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const j = await jsonOrThrow(resp);

    if (!j || !j.success) {
      throw new Error(j && j.error ? j.error : 'Server save/order creation failed');
    }
    if (!j.order || !j.key_id) {
      // saved OK but no order created
      return { saved: true, order: null };
    }

    // Open Razorpay Checkout
    const prefill = {
      name: options.prefillName || (form.querySelector('[name="full_name"]') ? form.querySelector('[name="full_name"]').value : ''),
      email: options.prefillEmail || (form.querySelector('[name="email"]') ? form.querySelector('[name="email"]').value : ''),
      contact: options.prefillContact || (form.querySelector('[name="mobile"]') ? form.querySelector('[name="mobile"]').value : '')
    };

    const paymentResult = await openRazorpay(j.key_id, j.order, prefill);
    // paymentResult contains razorpay_payment_id, razorpay_order_id, razorpay_signature
    return { saved: true, order: j.order, key_id: j.key_id, payment: paymentResult };
  }

  // expose globally
  global.doSaveAndPay = doSaveAndPay;
})(window);