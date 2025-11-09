// Lightweight payment helper for Save & Pay flow.
// Requires the Razorpay checkout script to be loaded by the page when used.

async function initiatePayment(csrfToken, amount) {
    const form = new URLSearchParams();
    form.append('csrf', csrfToken);
    form.append('amount', amount); // rupees
    const res = await fetch('/userpanel/payment/initiate_payment.php', {
        method: 'POST',
        body: form
    });
    return res.json();
}

async function verifyPayment(csrfToken, razorpay_payment_id, razorpay_order_id, razorpay_signature) {
    const form = new URLSearchParams();
    form.append('csrf', csrfToken);
    form.append('razorpay_payment_id', razorpay_payment_id);
    form.append('razorpay_order_id', razorpay_order_id);
    form.append('razorpay_signature', razorpay_signature);
    const res = await fetch('/userpanel/payment/verify_payment.php', {
        method: 'POST',
        body: form
    });
    return res.json();
}

function openRazorpayCheckout(keyId, orderId, amountPaise, userDisplay, handler) {
    const options = {
        key: keyId,
        amount: amountPaise,
        currency: 'INR',
        order_id: orderId,
        name: 'LBL Registration',
        description: 'Player registration fee',
        prefill: { name: userDisplay || '', email: '' },
        theme: { color: '#2563eb' },
        handler: handler
    };
    const rzp = new Razorpay(options);
    rzp.open();
    return rzp;
}

// Main helper to be called from page:
// doSaveAndPay({csrf, amount})
async function doSaveAndPay(opts) {
    const { csrf, amountRupees } = opts;
    // 1) initiate server order
    const res = await initiatePayment(csrf, amountRupees);
    if (!res.ok) throw new Error(res.error || 'Failed to initiate payment');
    const orderId = res.order_id;
    const keyId = res.key_id;
    const amountPaise = res.amount_paise;

    // 2) ensure checkout script loaded
    if (typeof Razorpay === 'undefined') {
        // dynamically load
        await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://checkout.razorpay.com/v1/checkout.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    // 3) open checkout
    return new Promise((resolve, reject) => {
        openRazorpayCheckout(keyId, orderId, amountPaise, '', async function (response) {
            // response has razorpay_payment_id, razorpay_order_id, razorpay_signature
            try {
                const verify = await verifyPayment(csrf,
                    response.razorpay_payment_id,
                    response.razorpay_order_id,
                    response.razorpay_signature
                );
                if (verify.ok) {
                    // redirect to return page
                    window.location.href = '/userpanel/payment/return.php?payment_id=' + encodeURIComponent(response.razorpay_payment_id);
                    resolve(verify);
                } else {
                    reject(new Error(verify.error || 'Verification failed'));
                }
            } catch (e) {
                reject(e);
            }
        });
    });
}