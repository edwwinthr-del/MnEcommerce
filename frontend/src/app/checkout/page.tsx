import { CheckoutPage } from "@/components/checkout-page";
export const metadata = {title: "Checkout", robots: {index: false, follow: false}};
export const dynamic = "force-dynamic";
export default async function Page({searchParams}: {searchParams: Promise<{coupon?: string}>}) {
  const {coupon} = await searchParams;
  return <CheckoutPage initialCoupon={typeof coupon === "string" ? coupon : ""}/>;
}
