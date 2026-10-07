export function PolicyContact() {
  const email = process.env.NEXT_PUBLIC_SUPPORT_EMAIL;
  const merchant = process.env.NEXT_PUBLIC_MERCHANT_NAME;
  const address = process.env.NEXT_PUBLIC_MERCHANT_ADDRESS;
  return <section><h2>Contact the store</h2>{merchant && <p>{merchant}{address ? ` · ${address}` : ""}</p>}{email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? <p>Email <a href={`mailto:${email}`}>{email}</a>. Include your order number for order questions. Never send passwords or payment card details.</p> : <p>Customer service contact details have not been published yet. This storefront is being prepared for launch; please do not place a real order until the merchant’s contact and business details are available.</p>}</section>;
}
