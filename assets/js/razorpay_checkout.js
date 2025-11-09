/**
 * assets/js/razorpay_checkout.js
 *
 * Client helper to create a Razorpay order (via your server) and open Razorpay Checkout.
 *
 * Usage (example):
 *
 * <script>
 *   // Provide key and endpoints from server-side template or inline script
 *   RazorpayCheckout.init({
 *     keyId: 'rzp_test_XXXX', // required (inject from server-side config)
 *     createOrderUrl: '/userpanel/razorpay_create_order.php',
 *     callbackUrl: '/userpanel/razorpay_callback.php',
 *     prefill: { name: 'Player Name', contact: '9999999999', email: 'a@b.com' },
 *     theme: { color: '#2563eb' },
 *   });
 *
 *   // On Pay button click:
 *   document.getElementById('payBtn').addEventListener('click', async function(){
 *     const amountRupees = '199.00'; // or amount_paise integer
 *     const res = await RazorpayCheckout.createAndPay({ amount: amountRupees, receipt_note: 'Player reg fee' });
 *     console.log('payment result', res);
 *   });
 * </script>
 *
 * Notes:
 * - This file does not embed your key; pass keyId from server (template) to avoid exposing secrets.
 * - Server endpoints must verify auth, create Razorpay order and return the order object (razorpay order)
 *   in JSON { success: true, order: { id, amount, currency, ... } }.
 * - After checkout success, this helper posts the razorpay response to callbackUrl for server-side verification.
 */

const RazorpayCheckout = (function () {
  const DEFAULTS = {
    createOrderUrl: '/userpanel/razorpay_create_order.php',
    callbackUrl: '/userpanel/razorpay_callback.php',
    keyId: '', // MUST be set via init()
    prefill: {}, // { name, email, contact }
    notes: {},
    theme: { color: '#2563eb' },
    modal: { escape: true },
    // Optional handlers
    onBeforeCreateOrder: null, // async fn(payload) => payload
    onOrderCreated: null,      // fn(orderObj)
    onPaymentSuccess: null,    // fn(serverResponse)
    onPaymentFailure: null,    // fn(error)
  };

  let cfg = Object.assign({}, DEFAULTS);

  function setConfig(options = {}) {
    cfg = Object.assign({}, cfg, options || {});
  }

  function _loadRazorpayScript() {
    // Returns a Promise that resolves when Razorpay checkout script is loaded
    if (window.Razorpay) return Promise.resolve(true);
    return new Promise((resolve, reject) => {
      const existing = document.querySelector('script[src="https://checkout.razorpay.com/v1/checkout.js"]');
      if (existing) {
        existing.addEventListener('load', () => resolve(true));
        existing.addEventListener('error', () => reject(new Error('Failed to load Razorpay script')));
        return;
      }
      const s = document.createElement('script');
      s.src = 'https://checkout.razorpay.com/v1/checkout.js';
      s.async = true;
      s.onload = () => resolve(true);
      s.onerror = () => reject(new Error('Failed to load Razorpay script'));
      document.head.appendChild(s);
    });
  }

  async function createOrderOnServer(payload = {}) {
    // payload expected: { amount (string rupees) OR amount_paise (int), receipt_note (opt) }
    // Call createOrderUrl and return parsed JSON (throws on network errors)
    const body = payload;
    // Allow hook to mutate payload
    if (typeof cfg.onBeforeCreateOrder === 'function') {
      try {
        const maybe = cfg.onBeforeCreateOrder(body);
        if (maybe instanceof Promise) {
          await maybe;
        }
      } catch (err) {
        // ignore hook errors but log
        console.error('onBeforeCreateOrder hook error', err);
      }
    }

    const res = await fetch(cfg.createOrderUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const txt = await res.text();
      throw new Error('createOrder failed: ' + res.status + ' ' + txt);
    }
    const json = await res.json();
    if (!json || !json.success) {
      throw new Error('createOrder response error: ' + (json && (json.error || JSON.stringify(json)) || 'unknown'));
    }
    if (typeof cfg.onOrderCreated === 'function') {
      try { cfg.onOrderCreated(json.order); } catch (e) { console.warn('onOrderCreated hook failed', e); }
    }
    return json.order;
  }

  async function postPaymentResultToServer(payload = {}) {
    // payload must include razorpay_order_id, razorpay_payment_id, razorpay_signature
    const res = await fetch(cfg.callbackUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const json = await res.json().catch(() => null);
    return { ok: res.ok, status: res.status, body: json };
  }

  function _buildCheckoutOptions(order, overrides = {}) {
    // order: Razorpay order object with id, amount, currency
    const options = {
      key: cfg.keyId,
      amount: order.amount, // amount in paise (integer)
      currency: order.currency || cfg.currency || 'INR',
      name: overrides.name || document.title || 'Payment',
      description: overrides.description || 'Payment',
      image: overrides.image || undefined,
      order_id: order.id,
      handler: function (response) {
        // response: { razorpay_payment_id, razorpay_order_id, razorpay_signature }
        // We will post to server to verify and finalize.
        (async () => {
          try {
            const serverPayload = {
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_order_id: response.razorpay_order_id,
              razorpay_signature: response.razorpay_signature,
            };
            const serverRes = await postPaymentResultToServer(serverPayload);
            if (serverRes.ok && serverRes.body && serverRes.body.success) {
              if (typeof cfg.onPaymentSuccess === 'function') {
                try { cfg.onPaymentSuccess(serverRes.body); } catch (e) { console.warn(e); }
              }
              // redirect if server asks so
              if (serverRes.body.redirect) {
                window.location.href = serverRes.body.redirect;
              }
            } else {
              if (typeof cfg.onPaymentFailure === 'function') {
                try { cfg.onPaymentFailure(serverRes.body || { error: 'Server verification failed' }); } catch (e) { console.warn(e); }
              }
            }
          } catch (err) {
            console.error('Error posting payment result to server', err);
            if (typeof cfg.onPaymentFailure === 'function') {
              try { cfg.onPaymentFailure({ error: err.message }); } catch (e) { console.warn(e); }
            }
          }
        })();
      },
      prefill: cfg.prefill || {},
      notes: cfg.notes || {},
      theme: cfg.theme || {},
      modal: cfg.modal || {},
    };
    return options;
  }

  async function createAndPay(opts = {}) {
    // opts: { amount (string rupees) OR amount_paise (int), receipt_note, name, description, prefill, notes }
    if (!cfg.keyId) throw new Error('Razorpay public keyId not configured. Call RazorpayCheckout.init({ keyId: "..." })');

    const payload = {};
    if (opts.amount_paise) payload.amount_paise = opts.amount_paise;
    if (opts.amount) payload.amount = opts.amount;
    if (opts.receipt_note) payload.receipt_note = opts.receipt_note;

    const order = await createOrderOnServer(payload); // may throw

    await _loadRazorpayScript();

    const checkoutOptions = _buildCheckoutOptions(order, {
      name: opts.name,
      description: opts.description,
      image: opts.image,
    });

    // If prefill passed in opts, merge
    if (opts.prefill) checkoutOptions.prefill = Object.assign({}, checkoutOptions.prefill, opts.prefill);
    if (opts.notes) checkoutOptions.notes = Object.assign({}, checkoutOptions.notes, opts.notes);

    const rzp = new window.Razorpay(checkoutOptions);
    // attach event listeners for modal failures / dismissal
    rzp.on('payment.failed', function (err) {
      // note: on payment.failed, handler is not invoked; we call onPaymentFailure
      if (typeof cfg.onPaymentFailure === 'function') {
        try { cfg.onPaymentFailure(err.error || err); } catch (e) { console.warn(e); }
      }
    });
    rzp.open();
    return true;
  }

  // Public API
  return {
    init: function (options = {}) {
      setConfig(options);
      if (!cfg.keyId) {
        console.warn('RazorpayCheckout.init: keyId not provided. Provide keyId to init the checkout (public key).');
      }
    },
    createOrder: createOrderOnServer,
    createAndPay: createAndPay,
    postPaymentResult: postPaymentResultToServer,
    loadScript: _loadRazorpayScript,
    config: function () { return Object.assign({}, cfg); },
  };
})();








