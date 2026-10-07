import type { Metadata } from "next";
import { Header } from "@/components/header";
import { Footer } from "@/components/footer";
import { siteUrl } from "@/lib/format";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl()),
  title: { default: "Mora — Good things for your everyday", template: "%s | Mora" },
  description: "Thoughtfully selected tech, home essentials and accessories. Discover your next everyday favourite, delivered across Montenegro.",
  openGraph: { type: "website", siteName: "Mora", locale: "en_ME" },
  robots: process.env.NEXT_PUBLIC_STORE_LIVE === "true" ? {index: true, follow: true} : {index: false, follow: false},
};

export default function RootLayout({children}: Readonly<{children: React.ReactNode}>) {
  return <html lang="en"><body><a href="#main-content" className="skip-link">Skip to content</a><Header/><main id="main-content">{children}</main><Footer/></body></html>;
}
