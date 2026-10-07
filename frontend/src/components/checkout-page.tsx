"use client";

import Link from "next/link";
import { useRef, useState, type FormEvent } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowUpRight, Check, LockKeyhole } from "lucide-react";
import { ApiError, postJson } from "@/lib/api";
import { useCart } from "@/lib/cart";
import { checkoutItems, itemKey } from "@/lib/cart-data";
import { money } from "@/lib/format";
import { usePreview } from "@/lib/use-preview";
import { checkoutSchema, type CheckoutFields } from "@/lib/validation";
import type { CheckoutItem, OrderReceipt } from "@/lib/types";
import { Breadcrumbs } from "./breadcrumbs";
import { CouponForm, OrderTotals } from "./order-summary";
import { ProductPhoto } from "./product-photo";

type OrderPayload = CheckoutFields & {items: CheckoutItem[]; coupon_code: string | null};
type Attempt = {key: string; body: OrderPayload};
const fields: {name: keyof CheckoutFields; label: string; autoComplete: string; type?: string; full?: boolean; placeholder?: string}[] = [
  {name: "customer_name", label: "Full name", autoComplete: "name", full: true},
  {name: "customer_email", label: "Email address", autoComplete: "email", type: "email"},
  {name: "customer_phone", label: "Phone number", autoComplete: "tel", type: "tel", placeholder: "+382"},
  {name: "shipping_address", label: "Street address and house number", autoComplete: "street-address", full: true},
  {name: "shipping_city", label: "City", autoComplete: "address-level2"},
  {name: "shipping_postal_code", label: "Postal code", autoComplete: "postal-code", placeholder: "81000"},
];

export function CheckoutPage({initialCoupon = ""}: {initialCoupon?: string}) {
  const cart = useCart();
  const [coupon, setCoupon] = useState(initialCoupon.slice(0, 50));
  const [receipt, setReceipt] = useState<OrderReceipt | null>(null);
  const [payment, setPayment] = useState<CheckoutFields["payment_method"]>("cash_on_delivery");
  const [message, setMessage] = useState("");
  const [uncertain, setUncertain] = useState(false);
  const [sending, setSending] = useState(false);
  const attempt = useRef<Attempt | null>(null);
  const busy = useRef(false);
  const preview = usePreview(cart.items, coupon, cart.ready && !receipt && !uncertain);
  const {register, handleSubmit, setError, formState: {errors}} = useForm<CheckoutFields>({resolver: zodResolver(checkoutSchema), defaultValues: {shipping_country: "ME", payment_method: "cash_on_delivery", customer_note: ""}});

  async function sendOrder(current: Attempt) {
    if (busy.current) return;
    busy.current = true;
    setSending(true);
    setMessage("");
    try {
      const result = await postJson<{data: OrderReceipt}>("/orders", current.body, {"Idempotency-Key": current.key});
      setPayment(current.body.payment_method);
      setReceipt(result.data);
      setUncertain(false);
      cart.clear();
      attempt.current = null;
      window.scrollTo({top: 0, behavior: "instant"});
    } catch (error) {
      // After an ambiguous result, throttling/session errors do not prove the first order failed.
      // Preserve its key until a receipt or a definitive validation rejection arrives.
      if (error instanceof ApiError && error.status >= 400 && error.status < 500 && (!uncertain || error.status === 422)) {
        attempt.current = null;
        setUncertain(false);
        for (const [name, messages] of Object.entries(error.errors)) {
          if (name in checkoutSchema.shape) setError(name as keyof CheckoutFields, {message: messages[0]}, {shouldFocus: true});
        }
        setMessage(error.message);
        preview.retry();
      } else {
        setUncertain(true);
        setMessage("We could not confirm whether your order arrived. Keep this page open and use ‘Check order again’ to safely repeat the same request without creating another order.");
      }
    } finally { busy.current = false; setSending(false); }
  }

  const submit = (event: FormEvent<HTMLFormElement>) => handleSubmit(values => {
    if (!preview.data || preview.loading || !cart.items.length || uncertain) return;
    const current = {key: crypto.randomUUID(), body: {...values, items: checkoutItems(cart.items), coupon_code: coupon || null}};
    attempt.current = current;
    return sendOrder(current);
  })(event);

  if (receipt) return <section className="receipt" aria-labelledby="receipt-title"><div className="receipt-icon"><Check size={30}/></div><p className="eyebrow">YOUR ORDER IS IN</p><h1 id="receipt-title">Thank you for shopping with Mora.</h1><p>Save your order number below. Your order is awaiting processing and payment has not yet been collected.</p><div className="receipt-details"><div className="summary-row"><span>Order number</span><strong data-testid="order-number">{receipt.order_number}</strong></div><div className="summary-row"><span>Total</span><strong>{money(receipt.total)}</strong></div><div className="summary-row"><span>Payment</span><span>{payment === "cash_on_delivery" ? "Cash on delivery" : "Bank transfer — awaiting payment"}</span></div><div className="summary-row"><span>Status</span><span>{receipt.order_status.replaceAll("_", " ")}</span></div></div><p>{payment === "cash_on_delivery" ? "Please have the order total ready when your delivery arrives." : "Wait for the store to provide verified bank details and your payment reference before making a transfer."}</p><p>Delivery estimates are shown on each product. You can find more information on our <Link href="/delivery" className="underline">delivery and returns page</Link>.</p><Link href="/shop" className="button button-dark">Continue exploring <ArrowUpRight size={17}/></Link></section>;
  if (!cart.ready) return <div role="status" className="empty-state">Loading your bag…</div>;
  if (!cart.items.length && !uncertain) return <div className="container empty-state"><h1>Your bag is empty.</h1><p>Add something you like before continuing to checkout.</p><Link href="/shop" className="button button-dark">Explore the collection</Link></div>;
  return <div className="container"><Breadcrumbs items={[{label: "Your bag", href: "/cart"}, {label: "Checkout"}]}/><div className="page-heading checkout-title"><div><h1>A few details. Then it’s yours.</h1><p>Guest checkout · Delivery within Montenegro</p></div><span><LockKeyhole size={15}/>Secure checkout</span></div><div className="checkout-layout"><form id="checkout-form" onSubmit={submit} noValidate><fieldset disabled={sending || uncertain} className="form-section"><legend><span>01</span>Your details & delivery</legend><div className="form-grid">{fields.map(field => <div className={`field ${field.full ? "full" : ""}`} key={field.name}><label htmlFor={field.name}>{field.label}</label><input id={field.name} type={field.type || "text"} autoComplete={field.autoComplete} placeholder={field.placeholder} inputMode={field.name === "shipping_postal_code" ? "numeric" : undefined} {...register(field.name)} aria-invalid={!!errors[field.name]} aria-describedby={errors[field.name] ? `${field.name}-error` : undefined}/>{errors[field.name] && <span className="field-error" id={`${field.name}-error`}>{errors[field.name]?.message}</span>}</div>)}<div className="field full"><label htmlFor="country">Country</label><input id="country" value="Montenegro" readOnly autoComplete="country-name"/><input type="hidden" {...register("shipping_country")}/></div><div className="field full"><label htmlFor="customer_note">Delivery note <span>(optional)</span></label><textarea id="customer_note" maxLength={1000} {...register("customer_note")} aria-invalid={!!errors.customer_note} aria-describedby={errors.customer_note ? "customer_note-error" : undefined}/>{errors.customer_note && <span className="field-error" id="customer_note-error">{errors.customer_note.message}</span>}</div></div></fieldset><fieldset disabled={sending || uncertain} className="form-section"><legend><span>02</span>How would you like to pay?</legend><div className="payment-options"><label className="payment-option"><input type="radio" value="cash_on_delivery" {...register("payment_method")}/><div><strong>Cash on delivery</strong><p>Pay the courier when your order arrives.</p></div></label><label className="payment-option"><input type="radio" value="bank_transfer" {...register("payment_method")}/><div><strong>Bank transfer</strong><p>The store will provide verified bank details before you pay. Your order remains unpaid until payment is confirmed.</p></div></label></div></fieldset><p className="checkout-consent">By placing your order, you agree to the <Link href="/terms">terms of sale</Link>. See how we use your delivery details in our <Link href="/privacy">privacy notice</Link>. All prices and the final order total are in EUR.</p>{message && <div className="form-error" role="alert">{message}</div>}{uncertain && <button type="button" className="button button-dark" disabled={sending} onClick={() => attempt.current && sendOrder(attempt.current)}>{sending ? "Checking your order…" : "Check order again"}</button>}</form><aside className="order-summary"><h2>Your order</h2><div className="checkout-summary-list">{cart.items.map(item => <div className="checkout-summary-item" key={itemKey(item)}><div className="checkout-summary-image"><ProductPhoto src={item.image} alt="" sizes="54px"/></div><div><h3>{item.name}</h3><p>{item.variant_name ? `${item.variant_name} · ` : ""}Quantity: {item.quantity}</p></div></div>)}</div><OrderTotals preview={preview.data} loading={preview.loading}/><CouponForm coupon={coupon} onApply={setCoupon} disabled={sending || uncertain || preview.loading}/>{preview.error && <div className="form-error" role="alert">{preview.error}<button type="button" onClick={preview.retry} className="block underline mt-2">Recheck prices</button></div>}<button type="submit" form="checkout-form" className="button button-dark button-wide" disabled={sending || uncertain || preview.loading || !preview.data}>{sending ? "Placing your order…" : `Place order${preview.data ? ` · ${money(preview.data.total)}` : ""}`}<ArrowUpRight size={17}/></button><p className="summary-note">Please check your details before placing the order.</p>{!sending && !uncertain && <Link href="/cart" className="text-link mt-4">Edit your bag</Link>}</aside></div></div>;
}
