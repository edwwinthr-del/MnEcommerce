import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import type { Product } from "@/lib/types";
import { money } from "@/lib/format";
import { ProductPhoto } from "./product-photo";

export function ProductCard({product, priority = false}: {product: Product; priority?: boolean}) {
  const image = product.images.find(image => image.is_primary) || product.images[0];
  return <article className="product-card"><Link className="product-image" href={`/products/${product.slug}`} tabIndex={-1} aria-hidden="true"><ProductPhoto src={image?.image_url} alt="" priority={priority}/>{product.is_new && <span className="product-badge">JUST LANDED</span>}{!product.can_purchase && <span className="stock-badge">Currently unavailable</span>}<span className="product-arrow"><ArrowUpRight size={19}/></span></Link><div className="product-info"><p className="product-category">{product.category?.name || product.brand || "The collection"}</p><h3><Link href={`/products/${product.slug}`}>{product.name}</Link></h3><div className="price"><span>{money(product.selling_price)}</span>{product.old_price && product.old_price > product.selling_price ? <del>{money(product.old_price)}</del> : null}</div><p className="product-delivery">{product.estimated_delivery_min}–{product.estimated_delivery_max} business days</p></div></article>;
}
