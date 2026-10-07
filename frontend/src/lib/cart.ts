"use client";

import { useSyncExternalStore } from "react";
import { CART_KEY, MAX_QUANTITY, itemKey, mergeItem, parseCart } from "./cart-data";
import type { CartItem } from "./types";

const initial = { items: [] as CartItem[], ready: false };
let state = initial;
const listeners = new Set<() => void>();
function notify() { listeners.forEach(listener => listener()); }
function save(items: CartItem[]) {
  state = { items, ready: true };
  try { window.localStorage.setItem(CART_KEY, JSON.stringify(items)); } catch { /* In private mode, the cart still works for this visit. */ }
  notify();
}
function onStorage(event: StorageEvent) {
  if (event.key === CART_KEY || event.key === null) { state = { items: parseCart(event.newValue), ready: true }; notify(); }
}
function subscribe(listener: () => void) {
  listeners.add(listener);
  if (!state.ready) {
    let items: CartItem[] = [];
    try { items = parseCart(window.localStorage.getItem(CART_KEY)); } catch { /* Storage is optional. */ }
    state = { items, ready: true }; notify();
  }
  if (listeners.size === 1) window.addEventListener("storage", onStorage);
  return () => { listeners.delete(listener); if (!listeners.size) window.removeEventListener("storage", onStorage); };
}
const getSnapshot = () => state;
const getServerSnapshot = () => initial;

export function useCart() {
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  return { ...snapshot,
    count: snapshot.items.reduce((sum, item) => sum + item.quantity, 0),
    add: (item: CartItem) => save(mergeItem(state.items, item)),
    remove: (key: string) => save(state.items.filter(item => itemKey(item) !== key)),
    update: (key: string, quantity: number) => save(state.items.map(item => itemKey(item) === key ? { ...item, quantity: Math.max(1, Math.min(MAX_QUANTITY, Math.trunc(quantity) || 1)) } : item)),
    clear: () => save([]),
  };
}
