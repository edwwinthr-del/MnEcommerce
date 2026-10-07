"use client";

import Image from "next/image";
import { useState } from "react";
import { safeImage } from "@/lib/format";

export function ProductPhoto({src, alt, priority = false, sizes = "(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw", className = ""}: {src?: string | null; alt: string; priority?: boolean; sizes?: string; className?: string}) {
  const [failed, setFailed] = useState(false);
  return <Image src={failed ? "/product-placeholder.svg" : safeImage(src)} alt={alt} fill sizes={sizes} priority={priority} className={className} onError={() => setFailed(true)} />;
}
