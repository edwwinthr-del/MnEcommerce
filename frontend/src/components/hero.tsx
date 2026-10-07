"use client";

import Link from "next/link";
import { useState } from "react";
import { ArrowRight, ArrowUpRight, ChevronLeft, ChevronRight } from "lucide-react";
import type { Banner } from "@/lib/types";
import { safeLink } from "@/lib/format";
import { ProductPhoto } from "./product-photo";

export function Hero({banners}: {banners: Banner[]}) {
  const [index, setIndex] = useState(0);
  const banner = banners[index];
  return <section className="hero" aria-label="Featured collections" aria-roledescription="carousel"><div className="hero-copy"><div className="eyebrow"><span/> SMALL UPGRADES. BIG DIFFERENCE.</div><h1>{banner?.title || <>Your everyday.<br/>A little <em>better.</em></>}</h1><p>{banner?.subtitle || "Considered essentials for your space, your soundtrack, and everything in between. Discover good things, chosen well."}</p><Link className="button button-dark" href={safeLink(banner?.button_url)}>{banner?.button_text || "Explore the collection"}<ArrowUpRight size={18}/></Link><div className="hero-bottom"><span>Thoughtfully selected.<br/><strong>Simply worth having.</strong></span>{banners.length > 1 ? <div className="hero-controls"><button className="icon-button" aria-label="Previous collection" onClick={() => setIndex((index - 1 + banners.length) % banners.length)}><ChevronLeft size={18}/></button><span aria-live="polite">{String(index + 1).padStart(2,"0")} / {String(banners.length).padStart(2,"0")}</span><button className="icon-button" aria-label="Next collection" onClick={() => setIndex((index + 1) % banners.length)}><ChevronRight size={18}/></button></div> : <span className="hero-edition">THE EVERYDAY EDIT / 01</span>}</div></div><div className="hero-visual"><ProductPhoto key={banner?.id || "default"} src={banner?.image || "https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1600&auto=format&fit=crop&q=85"} alt={banner?.title || "Over-ear headphones, a considered everyday essential"} priority sizes="(max-width: 720px) 100vw, 55vw"/><div className="hero-photo-label"><span>GOOD DESIGN.<br/>GREAT COMPANY.</span><ArrowUpRight size={28}/></div><Link href={safeLink(banner?.button_url || "/shop?category=audio")} className="hero-image-link">Find your everyday favourite <ArrowRight size={16}/></Link></div></section>;
}
