async function initiatePayment(csrfToken, amountRupees) {
  const form = new URLSearchParams();
  form.append('csrf', csrfToken);
  form.append('amount', amountRupees);
  const res = await fetch('payment/initiate_payment.php', {
    method: 'POST',
    body: form,
    credentials: 'same-origin'
  });
  const text = await res.text();
  try {
    return text ? JSON.parse(text) : { ok: false, error: 'Empty response', status: res.status, raw: text };
  } catch (err) {
    console.error('initiate_payment parse error', { status: res.status, raw: text });
    return { ok: false, error: 'Invalid server response', status: res.status, raw: text };
  }
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
  const text = await res.text();
  try {
    return text ? JSON.parse(text) : { ok: false, error: 'Empty response', status: res.status, raw: text };
  } catch (err) {
    console.error('verify_payment parse error', { status: res.status, raw: text });
    return { ok: false, error: 'Invalid server response', status: res.status, raw: text };
  }
}