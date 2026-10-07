"use client";
import { useState } from "react";
import { money } from "@/lib/format";
import type { Preview } from "@/lib/types";

export function OrderTotals({preview, loading}: {preview: Preview | null; loading: boolean}) {
  if (loading) return <div role="status" className="py-5 text-xs text-slate-600">Checking current prices and delivery…</div>;
  if (!preview) return <p className="text-xs leading-6 text-slate-600">Your final total will appear once the bag is validated.</p>;
  return <div aria-live="polite"><div className="summary-row"><span>Subtotal</span><span>{money(preview.subtotal)}</span></div><div className="summary-row"><span>Delivery</span><span>{preview.shipping_total === 0 ? "Free" : money(preview.shipping_total)}</span></div>{preview.discount_total > 0 && <div className="summary-row discount"><span>Discount</span><span>−{money(preview.discount_total)}</span></div>}<div className="summary-row total"><span>Total <small className="text-[10px] font-normal">EUR</small></span><span>{money(preview.total)}</span></div></div>;
}

export function CouponForm({coupon, onApply, disabled}: {coupon: string; onApply: (code: string) => void; disabled?: boolean}) {
  const [input, setInput] = useState(coupon);
  return coupon ? <div className="coupon-active"><span>Code: {coupon}</span><button type="button" disabled={disabled} onClick={() => {onApply(""); setInput("");}}>Remove code</button></div> : <div className="coupon-form"><label htmlFor="coupon">Have a discount code?</label><div className="coupon-input"><input id="coupon" type="text" maxLength={50} value={input} disabled={disabled} onChange={event => setInput(event.target.value.toUpperCase())} onKeyDown={event => {if (event.key === "Enter" && input.trim() && !disabled) {event.preventDefault(); onApply(input.trim());}}} placeholder="Enter code" autoCapitalize="characters"/><button type="button" disabled={!input.trim() || disabled} onClick={() => onApply(input.trim())}>Apply</button></div></div>;
}
