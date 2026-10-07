import type { MetadataRoute } from "next";
import { siteUrl } from "@/lib/format";
export default function robots(): MetadataRoute.Robots {
  if (process.env.NEXT_PUBLIC_STORE_LIVE !== "true") return {rules: {userAgent: "*", disallow: "/"}};
  return {rules: {userAgent: "*", allow: "/", disallow: ["/cart", "/checkout", "/account", "/api", "/admin"]}, sitemap: `${siteUrl()}/sitemap.xml`};
}
