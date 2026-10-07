"use client";
import { useEffect, useState } from "react";
import { postJson } from "./api";
import { checkoutItems } from "./cart-data";
import type { CartItem, Preview } from "./types";

export function usePreview(items: CartItem[], coupon: string, ready: boolean) {
  const request = JSON.stringify({items: checkoutItems(items), coupon_code: coupon || null});
  const [state, setState] = useState<{key: string; data: Preview | null; error: string | null}>({key: "", data: null, error: null});
  const [revision, setRevision] = useState(0);
  const key = `${request}:${revision}`;
  useEffect(() => {
    if (!ready || !JSON.parse(request).items.length) return;
    const controller = new AbortController();
    postJson<{data: Preview}>("/checkout/preview", JSON.parse(request), undefined, controller.signal)
      .then(result => { if (!controller.signal.aborted) setState({key,data:result.data,error:null}); })
      .catch(error => { if (!controller.signal.aborted) setState({key,data:null,error: error instanceof Error ? error.message : "Couldn’t calculate your order. Please try again."}); });
    return () => controller.abort();
  }, [request,key,ready]);
  return { data: state.key === key ? state.data : null, error: state.key === key ? state.error : null, loading: !ready || (items.length > 0 && state.key !== key), retry: () => setRevision(value => value+1) };
}
