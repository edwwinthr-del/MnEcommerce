"use client";

import Link from "next/link";
import { useState } from "react";
import { ArrowUpRight, Menu, Search, ShoppingBag, UserRound, X } from "lucide-react";
import { useCart } from "@/lib/cart";

export function Header() {
  const { count } = useCart();
  const [open, setOpen] = useState(false);
  return <>
    <div className="announcement"><div className="container announcement-inner"><span>Thoughtfully picked. Delivered across Montenegro.</span><Link href="/delivery">Delivery & returns <ArrowUpRight size={13} /></Link></div></div>
    <header className="site-header" onKeyDown={event => {if (event.key === "Escape") {setOpen(false); document.querySelector<HTMLButtonElement>(".mobile-menu")?.focus();}}}>
      <div className="container header-main">
        <Link href="/" className="wordmark" aria-label="Mora home">mora<span>®</span><i /></Link>
        <form action="/shop" role="search" className="header-search"><Search size={19} aria-hidden="true" /><label className="sr-only" htmlFor="site-search">Search products</label><input id="site-search" name="search" placeholder="Find your next everyday favourite" type="search" maxLength={120} /><button aria-label="Search products" type="submit"><ArrowUpRight size={19}/></button></form>
        <div className="header-actions"><Link href="/account" className="account-link"><UserRound size={19}/><span>Account</span></Link><Link href="/cart" className="bag-link" aria-label={`Shopping bag, ${count} ${count === 1 ? "item" : "items"}`}><ShoppingBag size={19}/><span className="bag-label">Bag</span><span className="bag-count" aria-hidden="true">{count}</span></Link><button className="mobile-menu icon-button" aria-expanded={open} aria-controls="mobile-navigation" aria-label={open ? "Close navigation" : "Open navigation"} onClick={() => setOpen(!open)}>{open ? <X/> : <Menu/>}</button></div>
      </div>
      <div className="nav-border"><nav aria-label="Main navigation" className="container main-nav"><Link href="/shop">Shop all <ArrowUpRight size={14}/></Link><Link href="/shop?category=audio">Audio</Link><Link href="/shop?category=home-living">Home & living</Link><Link href="/shop?category=everyday-tech">Everyday tech</Link><Link href="/shop?category=accessories">Accessories</Link><Link href="/shop?new=1" className="new-link">Just landed <span/></Link><span className="nav-note">Little upgrades. A better everyday.</span></nav></div>
      {open && <div className="mobile-panel" id="mobile-navigation"><form action="/shop" role="search"><label className="sr-only" htmlFor="mobile-search">Search products</label><input id="mobile-search" name="search" type="search" placeholder="Search the collection"/><button type="submit" className="icon-button" aria-label="Search"><Search size={20}/></button></form><nav aria-label="Mobile navigation">{[["Shop all", "/shop"], ["Audio", "/shop?category=audio"], ["Home & living", "/shop?category=home-living"], ["Everyday tech", "/shop?category=everyday-tech"], ["Accessories", "/shop?category=accessories"], ["My account", "/account"]].map(([name, href]) => <Link onClick={() => setOpen(false)} key={name} href={href}>{name}<ArrowUpRight size={16}/></Link>)}</nav></div>}
    </header>
  </>;
}
