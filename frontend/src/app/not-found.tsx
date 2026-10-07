import Link from "next/link";
export default function NotFound() { return <section className="container empty-state"><p className="eyebrow">404 · SOMETHING’S MISSING</p><h1>This page has moved on.</h1><p>The product or page you’re looking for isn’t available. There’s still plenty to discover.</p><Link className="button button-dark" href="/shop">Explore the collection</Link></section>; }
