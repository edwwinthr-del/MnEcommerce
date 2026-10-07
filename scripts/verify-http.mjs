import assert from "node:assert/strict";

const origin = new URL(process.argv[2] || "http://localhost:18080");
assert.ok(["http:", "https:"].includes(origin.protocol), "Expected an HTTP(S) origin");

async function request(path, status, options = {}) {
  const response = await fetch(new URL(path, origin), {
    redirect: "manual",
    signal: AbortSignal.timeout(15_000),
    ...options,
  });
  assert.equal(response.status, status, `${path}: unexpected HTTP status`);
  assert.match(response.headers.get("x-content-type-options") || "", /nosniff/i, `${path}: missing nosniff`);
  assert.match(response.headers.get("x-frame-options") || "", /DENY/i, `${path}: missing frame protection`);
  return response;
}

const unavailable = process.argv.includes("--database-unavailable");
const backendOnly = process.argv.includes("--backend-only");
const health = await request("/api/v1/health", unavailable ? 503 : 200);
assert.match(health.headers.get("cache-control") || "", /no-store/);
assert.deepEqual(await health.json(), { status: unavailable ? "unavailable" : "ok" });

if (!unavailable) {
  if (!backendOnly) {
    const home = await request("/", 200);
    const html = await home.text();
    assert.match(html, /Mora/);
    const asset = html.match(/(?:src|href)="([^"<>]*\/_next\/static\/[^"<>]+\.(?:js|css))"/);
    assert.ok(asset, "Storefront must reference a built Next.js asset");
    await request(asset[1].replaceAll("&amp;", "&"), 200);
  }

  const catalog = await request("/api/v1/products", 200);
  const products = (await catalog.json()).data;
  assert.ok(Array.isArray(products) && products.length > 0, "Expected the disposable demo catalog");
  assert.equal(JSON.stringify(products).includes('"purchase_price"'), false, "Public catalog exposes costs");
  await request(`/api/v1/products/${encodeURIComponent(products[0].slug)}`, 200);

  const admin = await request("/admin/login", 200);
  assert.match(await admin.text(), /livewire/i, "Admin route must reach Filament");

  const csrf = await request("/sanctum/csrf-cookie", 204);
  const cookies = csrf.headers.getSetCookie();
  assert.ok(cookies.some(cookie => cookie.startsWith("XSRF-TOKEN=")), "CSRF cookie missing");
  assert.ok(cookies.some(cookie => /;\s*httponly/i.test(cookie) && /;\s*samesite=lax/i.test(cookie)), "HttpOnly SameSite session cookie missing");
  const anonymous = await request("/api/v1/auth/me", 401, { headers: { Accept: "application/json" } });
  assert.match(anonymous.headers.get("cache-control") || "", /no-store/);
  await request("/api/v1/auth/login", 419, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ email: "smoke@example.test", password: "unused" }),
  });
  if (!backendOnly) {
    await request("/index.php", 404);
    await request("/.env", 403);
  }
}

console.log(unavailable ? "Database outage returns a private, uncached 503." : "HTTP smoke checks passed.");
