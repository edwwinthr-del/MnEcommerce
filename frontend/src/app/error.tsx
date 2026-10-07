"use client";
import Link from "next/link";
export default function ErrorPage({reset}: {error: Error & {digest?: string}; reset: () => void}) {
  return <section className="container empty-state"><h1>We couldn’t load this page.</h1><p>Please try again in a moment. Your saved bag is still here.</p><button className="button button-dark" onClick={reset}>Try again</button><Link className="text-link mt-5" href="/shop">Back to the shop</Link></section>;
}
