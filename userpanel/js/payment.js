// userpanel/js/payment.js
// Exposes window.doSaveAndPay so pages can call it.
// Note: this file uses page-relative endpoints: payment/initiate_payment.php and payment/verify_payment.php
// Place this file at: userpanel/js/payment.js

(function (window) {
  'use strict';

  async function initiatePayment(csrfToken, amountRupees) {
    const form = new URLSearchParams();
    form.append('csrf', csrfToken);
    form.append('amount', amountRupees); // rupees
    const res = await fetch('payment/initiate_payment.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });
    return res.json();
  }

  async function verifyPayment(csrfToken, razorpay_payment_id, razorpay_order_id, razorpay_signature) {
    const form = new URLSearchParams();
    form.append('csrf', csrfToken);
    form.append('razorpay_payment_id', razorpay_payment_id);
    form.append('razorpay_order_id', razorpay_order_id);
    form.append('razorpay_signature', razorpay_signature);
    const res = await fetch('payment/verify_payment.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });
    return res.json();
  }

  function openRazorpayCheckout(keyId, orderId, amountPaise, prefillName, handler) {
    const options = {
      key: keyId,
      amount: amountPaise,
      currency: 'INR',
      order_id: orderId,
      name: 'LBL Registration',
      description: 'Player registration fee',
      prefill: { name: prefillName || '' },
      theme: { color: '#2563eb' },
      handler: handler
    };
    const rzp = new Razorpay(options);
    rzp.open();
    return rzp;
  }

  // Expose main helper on window
  window.doSaveAndPay = async function (opts) {
    // opts: { csrf, amountRupees, prefillName (optional) }
    const csrf = opts.csrf;
    const amount = opts.amountRupees;
    const name = opts.prefillName || '';

    const res = await initiatePayment(csrf, amount);
    if (!res || !res.ok) {
      throw new Error(res && res.error ? res.error : 'Failed to initiate payment');
    }
    const orderId = res.order_id;
    const keyId = res.key_id;
    const amountPaise = res.amount_paise;

    // ensure checkout script loaded
    if (typeof Razorpay === 'undefined') {
      await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://checkout.razorpay.com/v1/checkout.js';
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
      });
    }

    return new Promise((resolve, reject) => {
      openRazorpayCheckout(keyId, orderId, amountPaise, name, async function (response) {
        try {
          const verify = await verifyPayment(csrf, response.razorpay_payment_id, response.razorpay_order_id, response.razorpay_signature);
          if (verify.ok) {
            // redirect to server return page showing receipt/status
            window.location.href = 'payment/return.php?payment_id=' + encodeURIComponent(response.razorpay_payment_id);
            resolve(verify);
          } else {
            reject(new Error(verify.error || 'Verification failed'));
          }
        } catch (err) {
          reject(err);
        }
      });
    });
  };

  // expose helpers for debug if needed
  window._paymentHelpers = { initiatePayment, verifyPayment };

})(window);