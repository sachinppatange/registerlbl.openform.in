// updated fetch handling: tolerant to non-JSON or empty responses
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
  let json;
  try {
    json = text ? JSON.parse(text) : null;
  } catch (e) {
    // Return structured error for caller
    return { ok: false, error: 'Invalid JSON response from server', raw: text, status: res.status };
  }

  return json;
}