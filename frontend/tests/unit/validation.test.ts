import assert from "node:assert/strict";
import { test } from "node:test";
import { checkoutSchema, passwordSchema, schemaForAuth } from "../../src/lib/validation.ts";
import { safeImage, safeLink } from "../../src/lib/format.ts";

const checkout = {customer_name: "Test Buyer", customer_email: "buyer@example.test", customer_phone: "+38267123456", shipping_address: "Test Street 1", shipping_city: "Podgorica", shipping_postal_code: "81000", shipping_country: "ME", payment_method: "cash_on_delivery", customer_note: ""};

test("checkout accepts Montenegro addresses and both supported payments", () => {
  assert.equal(checkoutSchema.safeParse(checkout).success, true);
  assert.equal(checkoutSchema.safeParse({...checkout, payment_method: "bank_transfer"}).success, true);
  for (const change of [{shipping_country: "US"}, {shipping_postal_code: "123"}, {customer_email: "invalid"}, {payment_method: "card"}, {customer_name: " "}]) {
    assert.equal(checkoutSchema.safeParse({...checkout, ...change}).success, false);
  }
});

test("new passwords enforce byte limits and confirmation without blocking legacy login", () => {
  assert.equal(passwordSchema.safeParse("a".repeat(72)).success, true);
  assert.equal(passwordSchema.safeParse("a".repeat(73)).success, false);
  assert.equal(passwordSchema.safeParse("é".repeat(37)).success, false);
  const user = {name: "Test Buyer", email: "buyer@example.test", password: "LongPassphrase!42", password_confirmation: "LongPassphrase!42"};
  assert.equal(schemaForAuth("register").safeParse(user).success, true);
  assert.equal(schemaForAuth("register").safeParse({...user, password_confirmation: "different"}).success, false);
  assert.equal(schemaForAuth("reset-password").safeParse({...user, password: "short"}).success, false);
  assert.equal(schemaForAuth("login").safeParse({...user, password: "legacy"}).success, true);
  assert.equal(schemaForAuth("forgot-password").safeParse({email: user.email}).success, true);
});

test("catalog links and images reject executable schemes and unapproved origins", () => {
  for (const value of ["javascript:alert(1)", "//external.test", "/\\external.test", "https://external.test", "/\nexternal.test"]) assert.equal(safeLink(value), "/shop");
  assert.equal(safeLink("/shop?category=audio"), "/shop?category=audio");
  for (const value of ["data:image/svg+xml,test", "http://images.unsplash.com/test", "https://external.test/photo", "/storage/../secret"]) assert.equal(safeImage(value), "/product-placeholder.svg");
  assert.equal(safeImage("/storage/products/photo.webp"), "/storage/products/photo.webp");
});
