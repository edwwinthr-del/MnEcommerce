import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ApiError, getProduct, getProducts } from "@/lib/api";
import { siteUrl, safeImage } from "@/lib/format";
import { Breadcrumbs } from "@/components/breadcrumbs";
import { ProductDetail } from "@/components/product-detail";
import { ProductCard } from "@/components/product-card";

export const dynamic = "force-dynamic";
type Props = { params: Promise<{slug: string}> };
export async function generateMetadata({params}: Props): Promise<Metadata> {
  const {slug} = await params;
  try { const {data: product} = await getProduct(slug); return { title: product.name, description: product.short_description, alternates: { canonical: `/products/${product.slug}` }, openGraph: { title: product.name, description: product.short_description, images: product.images[0] ? [{url: safeImage(product.images[0].image_url),alt: product.name}] : [] } }; } catch { return {title: "Product"}; }
}

export default async function ProductPage({params}: Props) {
  const {slug} = await params;
  let product;
  try { product = (await getProduct(slug)).data; } catch (error) { if (error instanceof ApiError && error.status === 404) notFound(); throw error; }
  const related = await getProducts(new URLSearchParams({category: product.category.slug})).catch(() => null);
  const structured = { "@context": "https://schema.org", "@type": "Product", name: product.name, description: product.short_description, sku: product.sku, image: product.images.map(image => safeImage(image.image_url)), brand: product.brand ? {"@type": "Brand", name: product.brand} : undefined, offers: {"@type": "Offer", url: `${siteUrl()}/products/${product.slug}`, priceCurrency: "EUR", price: (product.selling_price/100).toFixed(2), availability: `https://schema.org/${product.can_purchase ? "InStock" : "OutOfStock"}`, itemCondition: "https://schema.org/NewCondition"} };
  const specifications = product.specifications && Object.keys(product.specifications).length ? product.specifications : {Brand: product.brand || "Mora selection", SKU: product.sku, Category: product.category.name, Delivery: `${product.estimated_delivery_min}–${product.estimated_delivery_max} business days`};
  return <div className="container"><script type="application/ld+json" dangerouslySetInnerHTML={{__html: JSON.stringify(structured).replace(/</g,"\\u003c")}}/><Breadcrumbs items={[{label:"Shop",href:"/shop"},{label:product.category.name,href:`/shop?category=${product.category.slug}`},{label:product.name}]}/><ProductDetail product={product}/><section className="product-information"><div><h2>A little more detail</h2><p>{product.description}</p></div><div><h2>The essentials</h2><table className="specifications"><tbody>{Object.entries(specifications).map(([key,value]) => <tr key={key}><th scope="row">{key}</th><td>{String(value)}</td></tr>)}</tbody></table></div></section>{related && related.data.some(item => item.id !== product.id) && <section className="section pb-16"><div className="section-heading"><div><p className="eyebrow">GOOD COMPANY</p><h2>You might also like</h2></div></div><div className="product-grid">{related.data.filter(item => item.id !== product.id).slice(0,4).map(item => <ProductCard key={item.id} product={item}/>)}</div></section>}</div>;
}
