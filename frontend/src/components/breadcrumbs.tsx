import Link from "next/link";
import { ChevronRight } from "lucide-react";

export function Breadcrumbs({items}: {items: {label: string; href?: string}[]}) {
  return <nav aria-label="Breadcrumb" className="breadcrumbs"><Link href="/">Home</Link>{items.map((item, index) => <span key={index} className="contents"><ChevronRight aria-hidden="true"/>{item.href ? <Link href={item.href}>{item.label}</Link> : <span aria-current="page">{item.label}</span>}</span>)}</nav>;
}
