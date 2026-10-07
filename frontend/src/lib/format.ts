export function money(cents: number) {
  return new Intl.NumberFormat("en-IE", { style: "currency", currency: "EUR" }).format(cents / 100);
}

export function safeImage(value?: string | null): string {
  if (!value) return "/product-placeholder.svg";
  if (value.startsWith("/storage/") && !value.includes("\\") && !value.includes("..")) return value;
  try {
    const url = new URL(value);
    if (url.protocol === "https:" && url.hostname === "images.unsplash.com") return url.href;
  } catch { /* Untrusted catalog URLs use a local fallback. */ }
  return "/product-placeholder.svg";
}

export function safeLink(value?: string | null): string {
  if (value?.startsWith("/") && !value.startsWith("//") && !/[\\\u0000-\u0020]/.test(value)) return value;
  return "/shop";
}

export function siteUrl() { return (process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000").replace(/\/$/, ""); }
