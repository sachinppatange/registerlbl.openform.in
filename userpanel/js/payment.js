// robust initiatePayment: returns parsed JSON or structured error object
async function initiatePayment(csrfToken, amountRupees) {
  const form = new URLSearchParams();
  form.append('csrf', csrfToken);
  form.append('amount', amountRupees);

  const res = await fetch('payment/initiate_payment.php', {
    method: 'POST',
    body: form,
    credentials: 'same-origin'
  });

  const text = await res.text(); // always read as text first
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch (err) {
    // log raw response for debugging
    console.error('initiate_payment: failed to parse JSON', { status: res.status, raw: text });
    return { ok: false, error: 'Invalid server response (not JSON)', status: res.status, raw: text };
  }

  return json;
}