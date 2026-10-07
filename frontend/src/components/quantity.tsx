"use client";
import { Minus, Plus } from "lucide-react";
import { MAX_QUANTITY } from "@/lib/cart-data";

export function Quantity({value, onChange, label, max = MAX_QUANTITY}: {value: number; onChange: (value: number) => void; label: string; max?: number}) {
  const limit = Math.min(MAX_QUANTITY, Math.max(1,max));
  return <div className="quantity"><button type="button" aria-label={`Decrease ${label}`} disabled={value <= 1} onClick={() => onChange(Math.max(1,value-1))}><Minus size={13}/></button><input type="number" inputMode="numeric" min={1} max={limit} value={value} aria-label={label} onChange={event => onChange(Math.min(limit, Math.max(1, Number(event.target.value) || 1)))}/><button type="button" aria-label={`Increase ${label}`} disabled={value >= limit} onClick={() => onChange(Math.min(limit,value+1))}><Plus size={13}/></button></div>;
}
