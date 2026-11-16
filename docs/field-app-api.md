# Field Staff App API (`/api/app`)

Backend for the field staff / market boy app. It covers the Splash, Login, Dashboard, New Order, Product List, Attendance and Collection/Expense screens. Every call reads and writes the same tables as the Admin Panel (`orders`, `payments`, `stock_balances`, `attendances`, `expenses`). The app keeps no separate stock or money balances.

The examples below are real responses captured from the test suite. Pagination link arrays are trimmed, and timestamps are shown as they appear with `FIELDAPP_TIMEZONE=Asia/Kolkata`.

## Conventions

| Topic | Rule |
|---|---|
| Auth | `Authorization: Bearer <token>` on every call except login and forgot-password. |
| Format | JSON requests and responses. `POST /expenses` also accepts `multipart/form-data` (for the receipt file). |
| Success body | `{ "data": ... }`. Paginated lists also return `links` and `meta` (`current_page`, `last_page`, `per_page`, `total`). |
| Error body | `{ "message": "...", "code"?: "...", "errors"?: { field: [msg] } }` |
| Money | 2-decimal strings, e.g. `"280.00"`. Never send totals, prices, discounts or stock: the server ignores them. |
| Times | ISO-8601 in the business timezone (`config.timezone` from `/session`). The server clock is authoritative. |
| Pagination | `?page=N&per_page=M`, where `per_page` is at most 100. |
| Retries | `POST /orders`, `/collections` and `/expenses` **require** an `Idempotency-Key` header (8–64 chars, `[A-Za-z0-9_-]`; a UUID works). Generate one key per user action and reuse it on network retries. A replay returns the original record with HTTP 200 and `Idempotent-Replayed: true`; a first write returns 201. |

### Status codes

| Code | Meaning | App action |
|---|---|---|
| 200 / 201 | OK / created | |
| 401 | `unauthenticated`, `invalid_credentials`, `invalid_token` | Go to Login |
| 403 | `account_inactive`, `role_not_permitted` | Show message, go to Login (tokens have been revoked) |
| 404 | `not_found`: missing, or belongs to another distributor/staff member | |
| 409 | `attendance_conflict` (body `data` = current attendance state), `duplicate_shop` (body `data` = the existing shop) | Resync UI / select the existing shop |
| 422 | Validation (`errors`) or business rule (`message`, e.g. insufficient stock) | Show message |
| 429 | Rate limited (`Retry-After` header) | Back off |

Rate limits: login is limited to 5 failed attempts per email+IP (lockout shared with web login) and 20 requests/min per IP; authenticated reads to 120/min per user; writes to 30/min per user; forgot-password to 5/min.

---

## 1. Splash: `GET /session`

200 means go to Dashboard; 401 or 403 means go to Login. Returns the same `user`/`distributor`/`permissions`/`config` block as login, plus `token_expires_at`.

## 2. Login

### `POST /auth/login`
Sign in with **mobile number + password** (recommended for field staff). Admins create the user with a mobile number and password on Admin → Users & Staff.
```json
{ "mobile": "9876543210", "password": "secret-password", "device_name": "Redmi Note 13" }
```
`mobile` accepts `9876543210`, `+91 98765 43210` or `09876543210`. Email login still works: send `"email"` instead of `"mobile"`. A wrong mobile or password returns 401 `"Invalid mobile number or password."`; a malformed number returns 422 on `mobile`. The response's `data.user` now includes `"mobile"`.
```json
{
  "data": {
    "token": "1|slp_tzx6jjhXTRpA0nV39Rr1CMZ1gMiER1LRpimuTYUwd29fc663",
    "token_type": "Bearer",
    "expires_at": "2026-10-24T09:50:55+00:00",
    "user": { "id": 2, "name": "Ravi Kumar", "email": "ravi@example.com", "role": "staff", "status": "active" },
    "distributor": { "id": 1, "name": "Shree Pharma Distributors", "area": "Andheri", "phone": "9820000000", "status": "active" },
    "permissions": ["dashboard.view", "parties.view", "products.view", "stock.view_own_distributor", "orders.create",
      "orders.view_own", "collections.create", "collections.view_own", "expenses.create", "expenses.view_own",
      "attendance.mark", "attendance.view_own"],
    "config": {
      "timezone": "Asia/Kolkata",
      "payment_methods": ["cash", "upi", "bank_transfer", "cheque", "other"],
      "reference_required_methods": ["upi", "bank_transfer", "cheque"],
      "expense_categories": ["travel", "fuel", "food", "lodging", "phone", "courier", "stationery", "other"],
      "expense_category_options": [
        { "code": "fuel", "name": "Fuel", "receipt_required": true, "description_required": false, "max_amount": "2000.00" },
        { "code": "other", "name": "Other", "receipt_required": false, "description_required": true, "max_amount": null }
      ],
      "require_check_in": false,
      "geofence": { "configured": true, "mode": "flag", "latitude": 19.1197, "longitude": 72.8464, "radius_m": 300 }
    }
  }
}
```
Only active `staff` users of an active distributor can sign in. Errors: 401 `invalid_credentials`, 403 `role_not_permitted` / `account_inactive`, 429.

### `POST /auth/refresh`
Exchanges a still-valid token for a new one; the old token stops working immediately. Response: `{ "data": { "token", "token_type", "expires_at" } }`. Tokens last 7 days by default (`FIELDAPP_TOKEN_TTL_MINUTES`).

### `POST /auth/logout`
Revokes the current token. `{ "message": "Logged out." }`

### `POST /auth/forgot-password`
`{ "mobile": "9876543210" }` (or `{ "email": "..." }`) sends the standard reset email to the account's registered email address. No SMS gateway is configured, so nothing is sent to the phone. The user completes the reset on the web page `/reset-password/{token}`. The response is identical whether or not the account exists.

## 3. Dashboard: `GET /dashboard`

Every figure is calculated from live records for the signed-in staff member, for today's business date.
```json
{
  "data": {
    "date": "2026-10-17",
    "staff": { "id": 2, "name": "Ravi Kumar", "email": "ravi@example.com", "role": "staff" },
    "distributor": { "id": 1, "name": "Shree Pharma Distributors", "area": "Andheri" },
    "orders_today": { "count": 1, "value": "280.00" },
    "collections_today": { "count": 1, "amount": "560.00", "pending_verification": "560.00" },
    "expenses_today": { "submitted": "180.00", "approved": "0.00" },
    "shop_visits": { "tracked": false, "shops_served_today": 1 },
    "attendance": { "status": "checked_in", "record": { "...": "see Attendance" }, "geofence": { "...": "" } },
    "deliveries": { "confirmed": 1 },
    "quick_actions": {
      "new_order":  { "enabled": true,  "reason": null },
      "collection": { "enabled": true,  "reason": null },
      "expense":    { "enabled": true,  "reason": null },
      "check_in":   { "enabled": false, "reason": "already_checked_in" },
      "check_out":  { "enabled": true,  "reason": null }
    },
    "recent_orders": [ { "...": "up to 5 orders, same shape as GET /orders/{id}" } ]
  }
}
```
* `orders_today` excludes cancelled and failed orders.
* `shop_visits.tracked` is `false` because the system has no visit records. `shops_served_today` counts distinct shops with an order or collection today.
* `deliveries` counts your open orders by status (`confirmed`, `packed`, `out_for_delivery`).

## 4. New Order

### `GET /parties?search=&area=&per_page=`
Active shops of your distributor. `search` matches name, owner or phone.
```json
{ "data": [ { "id": 1, "name": "Gupta Medicals", "owner": "R. Gupta", "phone": "9876543210", "area": "Andheri West",
  "address": null, "credit_limit": "50000.00", "payment_terms_days": 15 } ], "links": {}, "meta": {} }
```

### `POST /parties`
Adds a shop for your distributor. Only `name` is required. The distributor is taken from your login, and the shop is active straight away.
```json
{ "name": "Gupta Medicals", "owner": "R. Gupta", "phone": "9876543210", "area": "Andheri West", "address": "Shop 4, SV Road" }
```
Limits: `name` and `owner` 255 characters, `phone` 30, `area` 100, `address` 500. Credit limit and payment terms cannot be sent from the app: new shops get ₹0 credit and the default 15 days, and the distributor changes them in the Admin Panel.

**201** returns the new shop in the same shape as `GET /parties`.

**409** `duplicate_shop`: a shop with the same name in the same area, or with the same phone, already exists for your distributor. `data` holds that shop, so the app can select it instead. This also makes a network retry safe, so no `Idempotency-Key` is needed.
```json
{ "data": { "id": 1, "name": "Gupta Medicals", "owner": "R. Gupta", "phone": "9876543210", "area": "Andheri West",
  "address": "Shop 4, SV Road", "credit_limit": "0.00", "payment_terms_days": 15 },
  "message": "\"Gupta Medicals\" in Andheri West already exists. Use it instead of adding it again.", "code": "duplicate_shop" }
```
If that shop is inactive, the message says to ask the distributor to activate it.

### `GET /parties/{shopId}/invoices`
Open invoices for the shop, used for allocating a collection.
```json
{ "data": [ { "id": 1, "number": "INV-261017-00001", "issue_date": "2026-10-17", "due_date": "2026-11-01",
  "total": "560.00", "outstanding": "560.00", "overdue": false } ],
  "meta": { "shop": { "id": 1, "name": "Gupta Medicals" }, "outstanding_total": "560.00" } }
```

### `POST /orders` (requires `Idempotency-Key`)
```json
{ "shop_id": 1, "items": [ { "product_id": 1, "qty": 10 } ], "remarks": "Deliver before 11 AM" }
```
```json
{
  "data": {
    "id": 2, "number": "SO-261017-10CD3", "status": "confirmed",
    "shop": { "id": 1, "name": "Gupta Medicals" }, "staff": { "id": 2, "name": "Ravi Kumar" },
    "items": [ { "id": 2, "product_id": 1, "name": "Paracetamol 500mg (10 tabs)", "sku": "PCM500", "unit": "strip",
      "qty": 10, "delivered_qty": 0, "price": "25.00", "tax_rate": "12.00", "discount": "0.00",
      "subtotal": "250.00", "tax": "30.00", "total": "280.00" } ],
    "subtotal": "250.00", "discount_total": "0.00", "tax_total": "30.00", "total": "280.00",
    "invoice": null, "remarks": "Deliver before 11 AM", "failure_reason": null,
    "created_at": "2026-10-17T09:50:56+00:00", "delivered_at": null
  }
}
```
* Only `product_id` and `qty` are read from each item. Prices and tax come from the product master; repeated products are merged.
* Stock posting: the order **reserves** stock at your distributor's location. Stock is **deducted once**, when delivery is posted in the Admin Panel. Cancelling or failing an order releases the reservation.
* `422 { "message": "Insufficient available stock for Paracetamol 500mg (10 tabs)." }`: nothing is saved.
* `items` has 1–100 entries; `qty` is 1–100000; `shop_id` must be an active shop of your distributor.
* Discounts: there is no discount master yet, so every discount field is `"0.00"`.

### `GET /orders?status=&shop_id=&search=&from=YYYY-MM-DD&to=YYYY-MM-DD`
### `GET /orders/{id}`
Your orders only (created by you or assigned to you). Others return 404.

## 5. Products

### `GET /products?search=&category=&in_stock=1&per_page=`
`search` matches name or SKU. Only active products are listed. `available_qty` is the on-hand quantity at your distributor minus reservations.
```json
{
  "data": [ { "id": 1, "name": "Paracetamol 500mg (10 tabs)", "sku": "PCM500", "category": "Analgesic", "unit": "strip",
    "selling_price": "25.00", "tax_rate": "12.00", "price_incl_tax": "28.00", "available_qty": 180, "tracking": "quantity" } ],
  "links": {}, "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 },
  "filters": { "categories": ["Analgesic"] }
}
```

### `GET /products/{id}`
Same shape. Inactive or unknown products return 404.

**Serial numbers and images:** the inventory is quantity-tracked, so every product has `"tracking": "quantity"` and no serial selection exists. Products have no image field yet.

## 5b. Cart (build an order, then check out)

One cart per **staff member per shop**, so a staff member can keep carts open for several shops. The cart stores only product and quantity: prices, GST and availability are recalculated live on every response, so totals are never stale. Stock is **not** reserved until checkout. Products have no variants: each pack size is its own product.

**Base URL:** `https://slhomeopharma.com/api/app` · **Headers on every call:**
```
Authorization: Bearer <token from POST /auth/login>
Accept: application/json
Content-Type: application/json
```

| # | Method & path | Body / query | Does |
|---|---|---|---|
| 1 | `GET /cart?shop_id=1` | | Cart with live totals (empty cart if none) |
| 2 | `GET /carts` | | Shops with an open cart |
| 3 | `POST /cart/items` | `{ "shop_id": 1, "product_id": 5, "qty": 3 }` | **Add to cart.** If the product is already there, the quantities are **combined** |
| 4 | `PATCH /cart/items/{productId}` | `{ "shop_id": 1, "qty": 7 }` | Sets the exact quantity; `0` removes the line |
| 5 | `DELETE /cart/items/{productId}?shop_id=1` | | Removes one product |
| 6 | `DELETE /cart?shop_id=1` | | Empties the cart |
| 7 | `POST /cart/checkout` | `{ "shop_id": 1, "remarks": "…" }` + **`Idempotency-Key`** header | Places a confirmed order and empties the cart |

### 1. Get cart: `GET /cart?shop_id=1`
Returns the cart (shape below). If the shop has no cart yet, `items` is `[]` and every total is `"0.00"`.

### 2. Open carts: `GET /carts`
```json
{ "data": [ { "shop": { "id": 1, "name": "Gupta Medicals" }, "line_count": 2, "updated_at": "2026-11-01T10:20:00+05:30" } ] }
```

### 3. Add to cart: `POST /cart/items`
```json
{ "shop_id": 1, "product_id": 5, "qty": 3 }
```
- **Same product again:** adding `qty: 2` after the call above leaves **one line with qty 5**, never a duplicate line.
- **Validation:** `qty` must be 1–100,000, and the combined quantity can't exceed available stock. Error example: `"You already have 3 in the cart. Only 4 bottle of Gas Care Drop 30ml available."`

### 4. Change quantity: `PATCH /cart/items/5`
```json
{ "shop_id": 1, "qty": 7 }
```
`qty: 0` removes the line. Returns 422 on `product_id` if the product isn't in the cart.

### 5. Remove one product: `DELETE /cart/items/5?shop_id=1`
Returns the updated cart. Returns 422 if the product wasn't in the cart.

### 6. Clear cart: `DELETE /cart?shop_id=1`
```json
{ "data": { "shop": { "id": 1, "name": "Gupta Medicals" }, "items": [], "line_count": 0, "total": "0.00", "can_checkout": false, "...": "" }, "message": "Cart cleared." }
```

### 7. Checkout: `POST /cart/checkout`
```
Idempotency-Key: 7b1d0c1e-4f7a-4a0e-9a51-2a6f4b9d8c01
```
```json
{ "shop_id": 1, "remarks": "Deliver before 11 AM" }
```
- **201** returns the created order (same shape as `GET /orders/{id}`, `status: "confirmed"`), and the cart is emptied.
- **Retry with the same key:** **200**, the same order, header `Idempotent-Replayed: true`. No duplicate order.
- **Stock changed since adding:** **422** `{"code":"insufficient_stock","shortages":[{"product_id":5,"product":"…","ordered":5,"available":2}]}`. The order isn't created and the cart is kept.

### Cart response (calls 1, 3, 4, 5, 6)
```json
{ "data": {
  "shop": { "id": 1, "name": "Gupta Medicals" },
  "items": [ { "product_id": 5, "name": "Gas Care Drop 30ml", "sku": "SLH-GAS-30", "unit": "bottle", "qty": 5,
               "price": "175.00", "tax_rate": "5.00", "subtotal": "875.00", "tax": "43.75", "total": "918.75",
               "available_qty": 20, "issue": null } ],
  "line_count": 1, "unit_count": 5, "subtotal": "875.00", "discount_total": "0.00", "tax_total": "43.75", "total": "918.75",
  "can_checkout": true, "issues": [], "updated_at": "2026-11-01T10:20:00+05:30" } }
```
- **`issue` per line:** `null`, `"insufficient_stock"` (stock dropped below the cart quantity since it was added) or `"inactive"` (the product was withdrawn). `can_checkout` is `false` while any issue exists; `issues[]` holds readable messages.
- **Errors:**

  | Code | Field | When |
  |---|---|---|
  | 401 | — | Missing, invalid or expired token |
  | 422 | `qty` | More than available stock, below 1 (add) / 0 (update), or above 100,000 |
  | 422 | `product_id` | Inactive, unknown, or not in the cart; or more than 100 different products |
  | 422 | `shop_id` | Not an active shop of your distributor |
  | 422 | `cart` | Checkout with an empty cart, or one that contains a withdrawn product |
  | 422 | `idempotency_key` | Checkout without an `Idempotency-Key` header |
  | 429 | — | Too many requests (`Retry-After` header) |

- **Privacy:** each staff member sees only their own carts.
- **Checkout** goes through the same code as `POST /orders`: server prices, atomic stock reservation, and retry-safe with the same `Idempotency-Key`. It returns **201** with the order (same shape as `GET /orders/{id}`), or **200** + `Idempotent-Replayed: true` on a retry. If stock ran out meanwhile, it returns **422** `{"code":"insufficient_stock","shortages":[…]}` and **the cart is kept** so the user can lower quantities.

## 6. Attendance

### `GET /attendance/today`
```json
{ "data": { "date": "2026-10-17", "status": "not_checked_in", "record": null,
  "geofence": { "configured": true, "mode": "flag", "latitude": 19.1197, "longitude": 72.8464, "radius_m": 300 } } }
```
`status` is one of `not_checked_in`, `checked_in` or `checked_out`.

### `POST /attendance/check-in` → 201
```json
{ "latitude": 19.1199, "longitude": 72.8466, "accuracy_m": 14, "client_timestamp": "2026-10-17T15:20:55+05:30" }
```
```json
{ "data": { "date": "2026-10-17", "status": "checked_in", "record": {
  "id": 1, "date": "2026-10-17", "status": "checked_in",
  "check_in": { "at": "2026-10-17T15:20:56+05:30", "client_at": "2026-10-17T15:20:55+05:30",
    "location": { "latitude": 19.1199, "longitude": 72.8466, "accuracy_m": 14, "source": "device_reported" },
    "distance_m": 31, "within_geofence": true },
  "check_out": null, "worked_minutes": null, "remarks": null }, "geofence": { "...": "" } } }
```

### `POST /attendance/check-out` → 200
`{ "latitude", "longitude", "accuracy_m"?, "client_timestamp"?, "remarks"? }`. The response has the same shape, with `check_out` filled and `worked_minutes` set.

Rules:
* `latitude` must be in −90..90 and `longitude` in −180..180; `0,0` is rejected as a missing fix.
* One check-in per business day. A second check-in, or a second check-out, returns **409** with the current state. A check-out before check-in returns 422.
* `at` is server time; `client_at` is stored only for audit.
* Coordinates are reported as `"source": "device_reported"`. The server does not treat them as verified presence.
* Geofence (set per distributor in Admin → Distributors). Mode comes from `FIELDAPP_GEOFENCE_MODE`:
  `off` ignores it; `flag` (the default) records `within_geofence` and `distance_m`; `enforce` returns 422 when outside the radius, or when `accuracy_m` is coarser than `FIELDAPP_MAX_ACCURACY_M` (if set). With no geofence configured, both fields are `null`.
* The app must ask for location permission and tell the user why before reading GPS.

### `GET /attendance/history?from=&to=&per_page=`
Your own records, newest first.

## 7. Collections and Expenses

### `POST /collections` (requires `Idempotency-Key`)
```json
{ "shop_id": 1, "amount": "560.00", "method": "upi", "reference": "UTR402918273645",
  "allocations": [ { "invoice_id": 1, "amount": "560.00" } ], "remarks": "Full payment",
  "collected_at": "2026-10-17T15:10:00+05:30" }
```
```json
{ "data": { "id": 1, "receipt_number": "RC-261017-000001", "shop": { "id": 1, "name": "Gupta Medicals" },
  "amount": "560.00", "method": "upi", "reference": "UTR402918273645", "remarks": "Full payment",
  "status": "collected", "verified": false, "verified_at": null,
  "allocations": [ { "invoice_id": 1, "amount": "560.00" } ],
  "collected_at": "2026-10-17T15:10:00+05:30", "recorded_at": "2026-10-17T15:20:56+05:30" } }
```
* `method` is one of `config.payment_methods`. `reference` is required for `upi`, `bank_transfer` and `cheque`. Reusing the same reference for the same method at the same distributor returns 422.
* `amount` is from 0.01 to 10,000,000 with at most 2 decimals. `collected_at` is optional and may be up to 7 days in the past, never in the future.
* `allocations` is optional. Each invoice must belong to the shop and may not exceed its outstanding amount. Allocations may not exceed `amount`.
* New collections are always `status: "collected"`. They become `verified` only when Accounts or the Distributor verifies them in Admin → Payments & Collections.

### `GET /collections?status=collected|verified&shop_id=&from=&to=`
Collections you recorded.

### `POST /expenses` (requires `Idempotency-Key`)
JSON, or multipart when attaching `receipt` (jpg/png/pdf, at most 5 MB).
```json
{ "category": "fuel", "amount": "180.00", "expense_date": "2026-10-17", "description": "Bike fuel, Andheri route" }
```
```json
{ "data": { "id": 1, "category": "fuel", "amount": "180.00", "expense_date": "2026-10-17",
  "description": "Bike fuel, Andheri route", "has_receipt": false, "status": "submitted",
  "review_note": null, "reviewed_at": null, "submitted_at": "2026-10-17T15:20:56+05:30" } }
```
* Build the category picker from `config.expense_category_options` (from `/session`); the admin adds, renames and turns categories off under Admin → Staff Expenses → Expense categories, so do not hard-code them. Send the `code` as `category`; show `name`.
* `receipt` is required when the category has `receipt_required: true`; `description` is required when `description_required` (the `other` category). `amount` is at most the category's `max_amount` (when set) and never above 100,000. `expense_date` is today or up to 30 days back.
* `status` is `submitted` until reviewed in Admin → Staff Expenses (`approved` or `rejected`, with a note). An expense is auto-approved only when `FIELDAPP_EXPENSE_AUTO_APPROVE_UPTO` is set and the amount is at or below it.
* There is no edit or delete endpoint, so submitted and reviewed expenses cannot be changed from the app.

### `GET /expenses?status=submitted|approved|rejected&from=&to=`
Your own expenses.

---

## Server configuration (`config/fieldapp.php`, `.env`)

| Env | Default | Effect |
|---|---|---|
| `FIELDAPP_TIMEZONE` | app timezone (UTC) | Business day for "today" and attendance dates. **Set to `Asia/Kolkata`.** |
| `FIELDAPP_TOKEN_TTL_MINUTES` | 10080 (7 days) | Token lifetime |
| `FIELDAPP_GEOFENCE_MODE` | `flag` | `off` / `flag` / `enforce` |
| `FIELDAPP_MAX_ACCURACY_M` | unset | In enforce mode, reject GPS fixes coarser than this many metres |
| `FIELDAPP_REQUIRE_CHECK_IN` | `false` | Orders and collections require an open check-in |
| `FIELDAPP_EXPENSE_AUTO_APPROVE_UPTO` | unset | Auto-approve expenses at or below this amount |

Expense categories, amount limits and the backdating window are set in `config/fieldapp.php`.
