import type { MetadataRoute } from "next";
import { getCategories, getProducts } from "@/lib/api";
import { siteUrl } from "@/lib/format";
export const dynamic = "force-dynamic";
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  if (process.env.NEXT_PUBLIC_STORE_LIVE !== "true") return [];
  const origin = siteUrl();
  const routes: MetadataRoute.Sitemap = ["", "/shop", "/delivery", "/privacy", "/terms"].map(path => ({url: `${origin}${path}`}));
  const categories = await getCategories();
  routes.push(...categories.data.map(category => ({url: `${origin}/shop?category=${encodeURIComponent(category.slug)}`})));
  let page = 1;
  let lastPage = 1;
  do {
    const products = await getProducts(new URLSearchParams({page: String(page)}));
    routes.push(...products.data.map(product => ({url: `${origin}/products/${product.slug}`})));
    lastPage = products.meta?.last_page || 1;
    page++;
  } while (page <= lastPage);
  return routes;
}
