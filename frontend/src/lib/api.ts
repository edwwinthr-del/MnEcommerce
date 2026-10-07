import type { Banner, Category, Collection, Product } from "./types";

export class ApiError extends Error {
  constructor(public status: number, message: string, public errors: Record<string, string[]> = {}) {
    super(message); this.name = "ApiError";
  }
}

export async function parseResponse<T>(response: Response): Promise<T> {
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    const fallback = response.status === 429 ? "Too many attempts. Please wait a minute and try again." :
      response.status === 409 ? "This checkout attempt has changed. Please review your details and try again." :
        "We couldn’t complete that request. Please try again.";
    throw new ApiError(response.status, response.status < 500 && typeof body?.message === "string" ? body.message : fallback, body?.errors || {});
  }
  return body as T;
}

async function catalog<T>(path: string): Promise<T> {
  const origin = process.env.API_INTERNAL_URL || "http://127.0.0.1:8000/api/v1";
  const response = await fetch(`${origin.replace(/\/$/, "")}${path}`, { cache: "no-store", signal: AbortSignal.timeout(8000), headers: { Accept: "application/json" } });
  return parseResponse<T>(response);
}

export const getCategories = () => catalog<Collection<Category>>("/categories");
export const getBanners = () => catalog<Collection<Banner>>("/banners");
export const getProducts = (query: URLSearchParams = new URLSearchParams()) => catalog<Collection<Product>>(`/products?${query}`);
export const getProduct = (slug: string) => catalog<{data: Product}>(`/products/${encodeURIComponent(slug)}`);

export async function postJson<T>(path: string, body: unknown, headers?: Record<string, string>, signal?: AbortSignal): Promise<T> {
  const requestSignal = signal ? AbortSignal.any([signal, AbortSignal.timeout(25000)]) : AbortSignal.timeout(25000);
  const send = async (refresh = false) => {
    const csrf = await csrfToken(refresh, requestSignal);
    return fetch(`/api/v1${path}`, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": csrf, ...headers }, body: JSON.stringify(body), signal: requestSignal });
  };
  let response = await send();
  // CSRF rejection occurs before the controller; retry once with a fresh token.
  if (response.status === 419) response = await send(true);
  return parseResponse<T>(response);
}

export async function csrfToken(refresh = false, signal?: AbortSignal): Promise<string> {
  const read = () => document.cookie.split("; ").find(row => row.startsWith("XSRF-TOKEN="))?.slice("XSRF-TOKEN=".length);
  if (refresh || !read()) {
    const response = await fetch("/sanctum/csrf-cookie", { credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" }, signal });
    if (!response.ok) throw new ApiError(response.status, "A secure checkout connection could not be established. Please refresh and try again.");
  }
  const token = read();
  if (!token) throw new ApiError(419, "Your browser could not establish a secure session. Enable essential cookies and try again.");
  return decodeURIComponent(token);
}
