import assert from "node:assert/strict";
import { test } from "node:test";
import { checkoutItems, itemKey, MAX_LINES, mergeItem, parseCart } from "../../src/lib/cart-data.ts";

const item = {product_id: 1, variant_id: null, quantity: 1, selling_price: 2400, name: "Canvas tote", slug: "canvas-tote", image: "", variant_name: null};

test("persisted carts discard malformed data, duplicate lines and unsafe quantities", () => {
  for (const raw of [null, "broken", "{}", "null"]) assert.deepEqual(parseCart(raw), []);
  const rows = [item, item, {...item, product_id: 2, quantity: -1}, {...item, product_id: 3, quantity: 1.5}, {...item, product_id: 4, slug: "//external.test"}, {...item, product_id: 5, selling_price: Infinity}];
  assert.deepEqual(parseCart(JSON.stringify(rows)), [item]);
});

test("variant lines remain distinct and cart limits survive repeated additions", () => {
  const variant = {...item, variant_id: 2, quantity: 8};
  assert.notEqual(itemKey(item), itemKey(variant));
  assert.deepEqual(mergeItem([item], variant), [item, variant]);
  assert.equal(mergeItem([variant], variant)[0].quantity, 10);
  const full = Array.from({length: MAX_LINES}, (_, index) => ({...item, product_id: index + 1}));
  assert.equal(mergeItem(full, {...item, product_id: 100}).length, MAX_LINES);
  assert.equal(parseCart(JSON.stringify([...full, {...item, product_id: 100}])).length, MAX_LINES);
});

test("checkout sends only identifiers and quantity even if cached prices are changed", () => {
  assert.deepEqual(checkoutItems([{...item, selling_price: 1, name: "Tampered", quantity: 2}]), [{product_id: 1, variant_id: null, quantity: 2}]);
});
