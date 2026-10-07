"use client";
import Link from "next/link";
import { useState } from "react";
import { ArrowLeft, ArrowUpRight, ShoppingBag, ShieldCheck } from "lucide-react";
import { useCart } from "@/lib/cart";
import { itemKey } from "@/lib/cart-data";
import { money } from "@/lib/format";
import { usePreview } from "@/lib/use-preview";
import { Breadcrumbs } from "./breadcrumbs";
import { ProductPhoto } from "./product-photo";
import { Quantity } from "./quantity";
import { CouponForm, OrderTotals } from "./order-summary";

export function CartPage() {
  const cart = useCart();
  const [coupon, setCoupon] = useState("");
  const preview = usePreview(cart.items, coupon, cart.ready);
  if (!cart.ready) return <div className="empty-state" role="status">Loading your bag…</div>;
  if (!cart.items.length) return <div className="container empty-state"><ShoppingBag size={44} strokeWidth={1.3}/><h1>Your next favourite is out there.</h1><p>Your bag is empty. Explore our collection of useful things for your everyday.</p><Link href="/shop" className="button button-dark">Find something good <ArrowUpRight size={17}/></Link></div>;
  return <div className="container"><Breadcrumbs items={[{label: "Your bag"}]}/><div className="page-heading"><h1>Your bag <span className="text-slate-400 text-2xl">({cart.count})</span></h1><p>A few good things, ready for a new home.</p></div><div className="cart-layout"><div>{cart.items.map(item => { const authoritative = preview.data?.items.find(line => line.product_id === item.product_id && line.variant_id === item.variant_id); return <article className="cart-item" key={itemKey(item)}><Link href={`/products/${item.slug}`} className="cart-item-image" tabIndex={-1} aria-hidden="true"><ProductPhoto src={item.image} alt="" sizes="105px"/></Link><div><h2><Link href={`/products/${item.slug}`}>{item.name}</Link></h2><p>{item.variant_name || "Everyday essential"}</p><div className="cart-item-actions"><Quantity value={item.quantity} onChange={quantity => cart.update(itemKey(item),quantity)} label={`Quantity for ${item.name}`}/><button type="button" className="remove-button" onClick={() => cart.remove(itemKey(item))} aria-label={`Remove ${item.name}`}>Remove</button></div></div><div className="cart-item-price">{money(authoritative?.line_total ?? item.selling_price*item.quantity)}{!authoritative && <span className="block mt-1 text-[9px] text-slate-500">Estimated</span>}</div></article>; })}<p className="cart-footnote"><ShieldCheck size={17}/>Prices and availability are checked again at checkout.</p><Link href="/shop" className="text-link"><ArrowLeft size={15}/>Continue exploring</Link></div><aside className="order-summary"><h2>Order summary</h2><OrderTotals preview={preview.data} loading={preview.loading}/><CouponForm coupon={coupon} onApply={setCoupon} disabled={preview.loading}/>{preview.error && <div className="form-error" role="alert">{preview.error}<button className="block underline mt-2" onClick={preview.retry}>Try again</button></div>}{preview.data && !preview.loading ? <Link className="button button-dark button-wide" href={`/checkout${coupon ? `?coupon=${encodeURIComponent(coupon)}` : ""}`}>Continue to checkout <ArrowUpRight size={17}/></Link> : <button className="button button-dark button-wide" disabled>Continue to checkout</button>}<p className="summary-note">Guest checkout · No account needed<br/>Delivery across Montenegro</p></aside></div></div>;
}
