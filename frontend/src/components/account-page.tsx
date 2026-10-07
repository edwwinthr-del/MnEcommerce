"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, parseResponse, postJson } from "@/lib/api";

type Customer = {id: number; name: string; email: string; role: string};
export function AccountPage() {
  const router = useRouter();
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    fetch("/api/v1/auth/me", {credentials: "same-origin", cache: "no-store", headers: {Accept: "application/json"}, signal: controller.signal})
      .then(parseResponse<{data: Customer}>).then(result => setCustomer(result.data))
      .catch(error => { if (controller.signal.aborted) return; if (error instanceof ApiError && error.status === 401) router.replace("/account/login"); else setError("Your account could not be loaded. Please try again."); });
    return () => controller.abort();
  }, [router, revision]);
  async function logout() {
    setBusy(true); setError("");
    try { await postJson("/auth/logout", {}); setCustomer(null); router.replace("/account/login"); router.refresh(); }
    catch { setError("We could not sign you out. Please try again."); }
    finally { setBusy(false); }
  }
  return <section className="account-shell"><p className="eyebrow">YOUR MORA ACCOUNT</p><h1>{customer ? `Hello, ${customer.name}.` : "Your account"}</h1>{!customer && !error && <p role="status">Loading your account…</p>}{error && <div className="form-error" role="alert">{error}{!customer && <button className="block underline mt-2" onClick={() => {setError(""); setRevision(value => value + 1);}}>Try again</button>}</div>}{customer && <><p>You’re signed in as {customer.email}.</p><p>Your orders use the contact and delivery details you provide at checkout. Keep your order number when you need help with a purchase.</p><Link className="button button-dark" href="/shop">Explore the collection</Link><div className="account-links"><Link href="/account/forgot-password">Reset your password</Link><button className="underline" onClick={logout} disabled={busy}>{busy ? "Signing out…" : "Sign out"}</button></div></>}</section>;
}
