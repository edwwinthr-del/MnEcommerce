import { CartPage } from "@/components/cart-page";
export const metadata = { title: "Your bag", robots: {index: false, follow: false} };
export const dynamic = "force-dynamic";
export default function Page() { return <CartPage/>; }
