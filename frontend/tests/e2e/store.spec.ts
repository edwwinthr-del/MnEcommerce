import { expect, test, type Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";

test.beforeAll(() => {
  // Each viewport starts with fresh limits in the guarded disposable database.
  execFileSync(process.env.E2E_PHP_BINARY || "php", [
    ...JSON.parse(process.env.E2E_PHP_ARGUMENTS || "[]"),
    "tests/Support/prepare-browser.php", "--clear-rate-limits",
  ], {cwd: path.resolve("../backend"), env: {...process.env, APP_ENV: "testing"}, stdio: "pipe"});
});

async function addTote(page: Page) {
  await page.goto("/products/everyday-canvas-tote");
  await expect(page.getByRole("heading", {name: "Everyday canvas tote", exact: true})).toBeVisible();
  await page.getByRole("button", {name: "Add to bag", exact: true}).click();
  await expect(page.getByRole("status")).toContainText("Added to your bag");
}

async function fillCheckout(page: Page) {
  await page.getByLabel("Full name", {exact: true}).fill("Browser Test Buyer");
  await page.getByLabel("Email address", {exact: true}).fill("buyer@example.test");
  await page.getByLabel("Phone number", {exact: true}).fill("+38267123456");
  await page.getByLabel("Street address and house number").fill("Test Street 1");
  await page.getByLabel("City", {exact: true}).fill("Podgorica");
  await page.getByLabel("Postal code").fill("81000");
}

test("catalog search, variants, and responsive navigation", async ({page, isMobile}, testInfo) => {
  await page.goto("/");
  await page.screenshot({path: testInfo.outputPath("home.png"), fullPage: true});
  if (isMobile) {
    await page.getByRole("button", {name: "Open navigation"}).click();
    await expect(page.getByRole("navigation", {name: "Mobile navigation"})).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.getByRole("button", {name: "Open navigation"})).toBeFocused();
  }
  await page.goto("/shop?search=headphones");
  await expect(page.getByRole("heading", {name: "Studio wireless headphones"})).toBeVisible();
  await page.getByRole("heading", {name: "Studio wireless headphones"}).click();
  await page.getByRole("radio", {name: "Sand", exact: true}).check();
  await page.getByRole("button", {name: "Add to bag", exact: true}).click();
  await page.getByRole("link", {name: /Shopping bag, 1 item/}).click();
  await expect(page.locator(".cart-item")).toContainText("Sand");
  await expect(page.locator(".summary-row.total")).toContainText("€89.00");
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test("guest coupon checkout validates fields and accepts a real order", async ({page}, testInfo) => {
  await addTote(page);
  await page.goto("/cart");
  await expect(page.locator(".summary-row.total")).toContainText("€27.90");
  await page.getByLabel("Have a discount code?").fill("DOBRODOSLI10");
  await page.getByRole("button", {name: "Apply", exact: true}).click();
  await expect(page.locator(".summary-row.total")).toContainText("€25.50");
  await page.getByRole("link", {name: "Continue to checkout"}).click();
  await page.getByRole("button", {name: /Place order/}).click();
  await expect(page.locator("#customer_name-error")).toBeVisible();
  await fillCheckout(page);
  await page.screenshot({path: testInfo.outputPath("checkout.png"), fullPage: true});
  await page.getByRole("button", {name: /Place order/}).click();
  await expect(page.getByTestId("order-number")).toHaveText(/^MORA-/);
  await expect(page.locator(".receipt-details")).toContainText("€25.50");
  await expect(page.getByRole("link", {name: "Shopping bag, 0 items"})).toBeVisible();
});

test("lost order response preserves its idempotency key through a throttled retry", async ({page}) => {
  await addTote(page);
  await page.goto("/checkout");
  await fillCheckout(page);
  const attempts: {key: string | undefined; body: string | null}[] = [];
  let receipt: {order_number: string} | undefined;
  await page.route("**/api/v1/orders", async route => {
    attempts.push({key: route.request().headers()["idempotency-key"], body: route.request().postData()});
    if (attempts.length === 2) {
      await route.fulfill({status: 429, contentType: "application/json", body: JSON.stringify({message: "Too many attempts."})});
      return;
    }
    const response = await route.fetch();
    expect(response.ok()).toBe(true);
    if (attempts.length === 1) {
      receipt = (await response.json()).data;
      await route.abort("failed");
    } else await route.fulfill({response});
  });
  await page.getByRole("button", {name: /Place order/}).click();
  await expect(page.getByRole("button", {name: "Check order again", exact: true})).toBeVisible();
  await expect(page.getByLabel("Full name", {exact: true})).toBeDisabled();
  await page.getByRole("button", {name: "Check order again", exact: true}).click();
  await expect(page.getByRole("button", {name: "Check order again", exact: true})).toBeEnabled();
  await expect(page.getByLabel("Full name", {exact: true})).toBeDisabled();
  await page.getByRole("button", {name: "Check order again", exact: true}).click();
  await expect(page.getByTestId("order-number")).toHaveText(receipt!.order_number);
  expect(attempts).toHaveLength(3);
  expect(attempts[0].key).toBeTruthy();
  expect(attempts[1]).toEqual(attempts[0]);
  expect(attempts[2]).toEqual(attempts[0]);
});

test("customer registration, session, logout, and reset request", async ({page}, testInfo) => {
  await page.goto("/account/register");
  const email = `browser-${testInfo.project.name}-${Date.now()}@example.test`;
  await page.getByLabel("Full name", {exact: true}).fill("Browser Customer");
  await page.getByLabel("Email address", {exact: true}).fill(email);
  await page.getByLabel("New password", {exact: true}).fill("BrowserCustomer!42");
  await page.getByLabel("Confirm new password", {exact: true}).fill("BrowserCustomer!42");
  await page.getByRole("button", {name: "Create account", exact: true}).click();
  await expect(page.getByRole("heading", {name: "Hello, Browser Customer."})).toBeVisible();
  await page.getByRole("button", {name: "Sign out", exact: true}).click();
  await expect(page).toHaveURL(/\/account\/login$/);
  await page.getByLabel("Email address", {exact: true}).fill(email);
  await page.getByLabel("Password", {exact: true}).fill("BrowserCustomer!42");
  await page.getByRole("button", {name: "Sign in", exact: true}).click();
  await expect(page.getByRole("heading", {name: "Hello, Browser Customer."})).toBeVisible();
  await page.getByRole("link", {name: "Reset your password"}).click();
  await page.getByLabel("Email address", {exact: true}).fill(email);
  await page.getByRole("button", {name: "Send reset link"}).click();
  await expect(page.locator(".form-success")).toContainText("If an account matches");
});

test("real HTTP rejects a checkout mutation without CSRF", async ({request}) => {
  const response = await request.post("/api/v1/checkout/preview", {data: {items: [{product_id: 1, quantity: 1}]}, headers: {Accept: "application/json", Origin: process.env.E2E_BASE_URL || "http://127.0.0.1:3000"}});
  expect(response.status()).toBe(419);
});

test("administrator can open catalog, order details, and reports", async ({page}) => {
  const adminUrl = process.env.E2E_ADMIN_URL || "http://127.0.0.1:8000";
  expect(process.env.E2E_ADMIN_EMAIL, "Run through scripts/test-e2e.ps1 or prepare CI fixtures").toBeTruthy();
  expect(process.env.E2E_ADMIN_PASSWORD).toBeTruthy();
  await page.goto(`${adminUrl}/admin/login`);
  await page.getByLabel("Email address").fill(process.env.E2E_ADMIN_EMAIL!);
  await page.getByLabel(/Password/).fill(process.env.E2E_ADMIN_PASSWORD!);
  await page.getByRole("button", {name: "Sign in"}).click();
  await expect(page.getByRole("heading", {name: "Dashboard", exact: true})).toBeVisible();
  await page.goto(`${adminUrl}/admin/products`);
  await expect(page.getByRole("heading", {name: "Products", exact: true})).toBeVisible();
  await expect(page.getByRole("cell", {name: "Everyday canvas tote", exact: true})).toBeVisible();
  await page.goto(`${adminUrl}/admin/orders`);
  await page.getByRole("button", {name: "Details", exact: true}).first().click();
  await expect(page.getByLabel("Customer name", {exact: true})).toHaveValue("Browser Test Buyer");
  await page.goto(`${adminUrl}/admin/reports`);
  await expect(page.getByRole("heading", {name: "Reports", exact: true})).toBeVisible();
});
