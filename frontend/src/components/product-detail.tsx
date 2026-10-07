"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ArrowUpRight, ShoppingBag, Truck, RotateCcw, ShieldCheck } from "lucide-react";
import type { Product } from "@/lib/types";
import { money } from "@/lib/format";
import { useCart } from "@/lib/cart";
import { ProductPhoto } from "./product-photo";
import { Quantity } from "./quantity";

export function ProductDetail({product}: {product: Product}) {
  const router = useRouter();
  const cart = useCart();
  const [imageIndex, setImageIndex] = useState(0);
  const [variantId, setVariantId] = useState<number | null>((product.variants.find(variant => variant.can_purchase)?.id ?? product.variants[0]?.id) ?? null);
  const [quantity, setQuantity] = useState(1);
  const [added, setAdded] = useState(false);
  const variant = product.variants.find(variant => variant.id === variantId);
  const price = variant?.selling_price ?? product.selling_price;
  const stock = variant?.stock ?? product.stock;
  const available = product.can_purchase && (variant?.can_purchase ?? true) && (!product.track_stock || stock > 0);
  const images = [...product.images].sort((a,b) => Number(b.is_primary)-Number(a.is_primary) || a.sort_order-b.sort_order);
  function add(buy = false) {
    if (!available) return;
    cart.add({ product_id: product.id, variant_id: variantId, quantity, name: product.name, variant_name: variant?.name ?? null, slug: product.slug, selling_price: price, image: images[0]?.image_url || "" });
    setAdded(true);
    if (buy) router.push("/checkout");
  }
  return <div className="product-detail"><div className="gallery"><div className="gallery-main"><ProductPhoto key={imageIndex} src={images[imageIndex]?.image_url} alt={`${product.name}${images.length > 1 ? ` — view ${imageIndex+1}` : ""}`} priority sizes="(max-width: 600px) 100vw, 50vw"/></div>{images.length > 1 && <div className="gallery-thumbs" aria-label="Product images">{images.map((image,index) => <button key={image.id} onClick={() => setImageIndex(index)} aria-label={`Show image ${index+1} of ${product.name}`} aria-pressed={imageIndex === index}><ProductPhoto src={image.image_url} alt="" sizes="78px"/></button>)}</div>}</div><div className="detail-copy"><p className="eyebrow">{product.category?.name} {product.brand ? ` / ${product.brand}` : ""}</p><h1>{product.name}</h1><div className="price"><span>{money(price)}</span>{!variant && product.old_price && product.old_price > price ? <del>{money(product.old_price)}</del> : null}</div><p className="detail-description">{product.short_description}</p><p className={`availability ${available ? "" : "unavailable"}`}><span/>{available ? product.track_stock ? "In stock · Ready for your everyday" : "Available to order" : "Currently unavailable"}</p>{product.variants.length > 0 && <fieldset className="variant-picker"><legend>Choose your option</legend><div className="variant-options">{product.variants.map(option => <label key={option.id}><input type="radio" name="variant" value={option.id} checked={variantId === option.id} onChange={() => {setVariantId(option.id); setAdded(false); setQuantity(1);}}/><span>{option.name}{product.track_stock && option.stock === 0 ? " · Sold out" : ""}</span></label>)}</div></fieldset>}<div className="purchase-row"><Quantity value={quantity} onChange={value => {setQuantity(value); setAdded(false);}} label="Quantity" max={product.track_stock ? stock : 10}/><button className="button button-dark" onClick={() => add()} disabled={!available}><ShoppingBag size={17}/>Add to bag</button></div><button className="button button-outline button-wide" onClick={() => add(true)} disabled={!available}>Buy now <ArrowUpRight size={17}/></button><div className="add-status" role="status">{added && <>Added to your bag.<Link href="/cart">View bag →</Link></>}</div><div className="delivery-box"><div><Truck size={20} strokeWidth={1.5}/><div><strong>Delivery across Montenegro</strong><p>Estimated {product.estimated_delivery_min}–{product.estimated_delivery_max} business days. Final delivery cost at checkout.</p></div></div><div><RotateCcw size={20} strokeWidth={1.5}/><div><strong>Thoughtful shopping, clear information</strong><p>Read our <Link href="/delivery">delivery and returns information</Link> before you order.</p></div></div><div><ShieldCheck size={20} strokeWidth={1.5}/><div><strong>Pay with cash on delivery or bank transfer</strong><p>Your order total is confirmed before you place it.</p></div></div></div><p className="sku">SKU: {variant?.sku || product.sku}</p></div></div>;
}
