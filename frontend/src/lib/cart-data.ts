import type { CartItem, CheckoutItem } from "./types.ts";

export const CART_KEY = "mora.cart.v1";
export const MAX_QUANTITY = 10;
export const MAX_LINES = 50;
export function itemKey(item: Pick<CartItem, "product_id" | "variant_id">) { return `${item.product_id}:${item.variant_id ?? "base"}`; }

export function parseCart(raw: string | null): CartItem[] {
  try {
    const data: unknown = JSON.parse(raw || "[]");
    if (!Array.isArray(data)) return [];
    const items: CartItem[] = [];
    const keys = new Set<string>();
    for (const row of data.slice(0, MAX_LINES)) {
      if (!row || typeof row !== "object" || !Number.isSafeInteger(row.product_id) || row.product_id < 1 ||
        !(row.variant_id === null || (Number.isSafeInteger(row.variant_id) && row.variant_id > 0)) ||
        !Number.isSafeInteger(row.quantity) || row.quantity < 1 || row.quantity > MAX_QUANTITY ||
        !Number.isSafeInteger(row.selling_price) || row.selling_price < 0 ||
        typeof row.name !== "string" || typeof row.slug !== "string" || !/^[a-z0-9-]+$/.test(row.slug) || typeof row.image !== "string") continue;
      const item: CartItem = { product_id: row.product_id, variant_id: row.variant_id, quantity: row.quantity,
        selling_price: row.selling_price, name: row.name.slice(0, 200), slug: row.slug.slice(0, 200), image: row.image.slice(0, 2048),
        variant_name: typeof row.variant_name === "string" ? row.variant_name.slice(0, 100) : null };
      if (!keys.has(itemKey(item))) { items.push(item); keys.add(itemKey(item)); }
    }
    return items;
  } catch { return []; }
}

export function checkoutItems(items: CartItem[]): CheckoutItem[] {
  return items.map(({product_id, variant_id, quantity}) => ({product_id, variant_id, quantity}));
}

export function mergeItem(items: CartItem[], added: CartItem): CartItem[] {
  const key = itemKey(added);
  if (items.some(item => itemKey(item) === key)) return items.map(item => itemKey(item) === key ? {...added, quantity: Math.min(MAX_QUANTITY, item.quantity + added.quantity)} : item);
  return items.length >= MAX_LINES ? items : [...items, {...added, quantity: Math.min(MAX_QUANTITY, added.quantity)}];
}
