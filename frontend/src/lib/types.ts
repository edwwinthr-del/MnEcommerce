export type Category = { id: number; name: string; slug: string; description?: string; image?: string | null };
export type ProductImage = { id: number; image_url: string; is_primary: boolean; sort_order: number };
export type Variant = { id: number; name: string; sku: string; selling_price: number; stock: number; can_purchase: boolean };
export type Product = {
  id: number; name: string; slug: string; short_description: string; description: string;
  sku: string; brand: string | null; selling_price: number; old_price: number | null;
  stock: number; track_stock: boolean; is_featured: boolean; is_new: boolean;
  estimated_delivery_min: number; estimated_delivery_max: number; category: Category;
  images: ProductImage[]; variants: Variant[]; specifications?: Record<string, string>;
  can_purchase: boolean;
};
export type Banner = { id: number; title: string; subtitle: string; image: string; mobile_image?: string | null; button_text: string; button_url: string; position: string };
export type Collection<T> = { data: T[]; meta?: { current_page: number; last_page: number; per_page: number; total: number }; links?: { next?: string | null; prev?: string | null } };
export type CartItem = {
  product_id: number; variant_id: number | null; quantity: number;
  name: string; variant_name: string | null; slug: string; image: string;
  selling_price: number;
};
export type CheckoutItem = Pick<CartItem, "product_id" | "variant_id" | "quantity">;
export type Preview = {
  subtotal: number; shipping_total: number; discount_total: number; total: number; currency: "EUR";
  items: { product_id: number; variant_id: number | null; product_name: string; sku: string; quantity: number; selling_price: number; line_total: number }[];
  coupon_code: string | null;
};
export type OrderReceipt = { order_number: string; order_status: string; payment_status: string; total: number; currency: "EUR"; customer_email: string };
