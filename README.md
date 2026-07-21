# Enterprise Flash-Sale RESTful API Backend

An enterprise-grade, pure RESTful API backend built with **Laravel 13**, specifically designed to handle high-concurrency E-Commerce Flash Sale systems under heavy traffic without race conditions or product overselling.

---

## 📋 Task 1: Online Store Requirements Reference

This backend is designed to strictly satisfy the business and technical specifications of the flash sale task:

### 1. Business Requirements

1. **Order Structure:** An **Order** must consist of at minimum one **Order Item**.
   - *Implementation:* Enforced via input validation inside [StoreOrderRequest](file:///c:/Users/tmaul/Herd/online-store-api/Modules/Store/app/Http/Requests/StoreOrderRequest.php) using `'items' => ['required', 'array', 'min:1']` and structured logic checks in [CheckoutAction](file:///c:/Users/tmaul/Herd/online-store-api/Modules/Store/app/Actions/CheckoutAction.php) that aborts empty requests.
2. **Flash Sale Engine:** Products feature discounted flash sale pricing that applies automatically during active flash sale windows.
   - *Implementation:* Managed inside the [Product](file:///c:/Users/tmaul/Herd/online-store-api/Modules/Store/app/Models/Product.php) model using `isInFlashSale()` and `getActivePrice()`. If the current time falls between `flash_sale_start` and `flash_sale_end` and `flash_sale_price` is defined, the system automatically prices the order item at the discounted rate.
3. **Negative Stock Prevention (CRITICAL):** The system must guarantee that inventory stock never falls below `0`, even under severe bursts of concurrent requests.
   - *Implementation:* Enforced inside database transactions utilizing row-level pessimistic locking (`lockForUpdate()`) on the PostgreSQL database. This serializes concurrent checkouts for a given product, verifies the stock status, decrements the quantity, and throws a custom `OutOfStockException` if the request exceeds available inventory.

### 2. Technical Requirements

1. **RESTful API Solution:** The solution is designed as a modular API using JSON as its message format, returning proper HTTP response codes and clean error messages inside standardized envelopes.
2. **Race Condition Handling:** Employs row-level locking (`FOR UPDATE`) to serialize stock allocation during concurrent purchase spikes, preventing overselling.
3. **Functional Concurrency Test:** Features a command-line functional test simulating a real-world burst of orders on a single product.

---

## 🛠️ API Architecture & Endpoint Reference

All API communications strictly use **JSON** for request payloads and response bodies.

### Response Envelopes

* **Success Response (200 OK / 210 Created):**
  ```json
  {
    "success": true,
    "message": "Order created successfully.",
    "data": { ... },
    "errors": null
  }
  ```
* **Error Response (400 / 401 / 403 / 404 / 422 / 500):**
  ```json
  {
    "success": false,
    "message": "Insufficient stock for product: Flash Sale Item.",
    "data": null,
    "errors": null
  }
  ```

### Registered Endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| **POST** | `/api/v1/auth/register` | Guest | Registers a new user. Rate-limited to 5 requests/min. |
| **POST** | `/api/v1/auth/login` | Guest | Logs in a user. Returns a Bearer token. Rate-limited to 5 requests/min. |
| **POST** | `/api/v1/auth/logout` | Sanctum | Revokes the current active Sanctum token. |
| **GET** | `/api/v1/products` | Guest / Sanctum | Paginated list of active products (guest) or all products (admin). |
| **GET** | `/api/v1/products/{product}` | Guest / Sanctum | Details of a specific active product (guest) or any product (admin). |
| **POST** | `/api/v1/products` | Sanctum | Create a new product. |
| **PUT** | `/api/v1/products/{product}` | Sanctum | Update an existing product. |
| **DELETE** | `/api/v1/products/{product}` | Sanctum | Soft-delete a product. |
| **PATCH** | `/api/v1/products/{product}/toggle-status` | Sanctum | Toggle active status of a product. |
| **PATCH** | `/api/v1/products/{id}/restore` | Sanctum | Restore a soft-deleted product. |
| **DELETE** | `/api/v1/products/{id}/force-delete` | Sanctum | Permanently delete a soft-deleted product. |
| **POST/PATCH** | `/api/v1/products/bulk/*` | Sanctum | Bulk actions (delete, restore, force-delete, toggle-status). |
| **GET** | `/api/v1/orders` | Sanctum | Paginated list of user's orders (user) or all orders (admin). |
| **POST** | `/api/v1/orders` | Sanctum | Place a new order (user checkout) or create order (admin CRUD). |
| **GET** | `/api/v1/orders/{order}` | Sanctum | Retrieve details of an order owned by user or any order (admin). |
| **PUT** | `/api/v1/orders/{order}` | Sanctum | Update an existing order. Adjusts/refunds product stock. |
| **DELETE** | `/api/v1/orders/{order}` | Sanctum | Soft-delete an order. |
| **PATCH** | `/api/v1/orders/{order}/toggle-status` | Sanctum | Toggle active status of an order. |
| **PATCH** | `/api/v1/orders/{id}/restore` | Sanctum | Restore a soft-deleted order. |
| **DELETE** | `/api/v1/orders/{id}/force-delete` | Sanctum | Permanently delete a soft-deleted order. |
| **POST/PATCH** | `/api/v1/orders/bulk/*` | Sanctum | Bulk actions (delete, restore, force-delete, toggle-status). |

---

## 💡 API Quick-Start & Multilingual Usage

### 1. User Registration (`POST /api/v1/auth/register`)
- **Request Payload:**
  ```json
  {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }
  ```
- **Response Payload (201 Created):**
  ```json
  {
    "success": true,
    "message": "User registered successfully.",
    "data": {
      "user": {
        "id": 1,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "is_active": true
      },
      "access_token": "1|3Wv...",
      "token_type": "Bearer",
      "expires_at": "2026-07-20 20:53:13"
    },
    "errors": null
  }
  ```

### 2. User Login (`POST /api/v1/auth/login`)
- **Request Payload:**
  ```json
  {
    "email": "jane@example.com",
    "password": "password123"
  }
  ```
- **Response Payload (200 OK):**
  Matches the registration response format containing the generated Sanctum bearer token under the `access_token` key.

### 3. Place Order (`POST /api/v1/orders`)
- **Request Payload:**
  ```json
  {
    "items": [
      {
        "product_id": 123,
        "quantity": 2
      }
    ]
  }
  ```
- **Response Payload (201 Created):**
  ```json
  {
    "success": true,
    "message": "Order created successfully.",
    "data": {
      "id": 1,
      "user_id": 999,
      "status": "completed",
      "total_amount": "20.00",
      "items": [
        {
          "id": 1,
          "product_id": 123,
          "quantity": 2,
          "price": "10.00"
        }
      ],
      "created_at": "2026-07-20T11:52:11.000000Z",
      "updated_at": "2026-07-20T11:52:11.000000Z"
    },
    "errors": null
  }
  ```

### 4. Multilingual Requests & Locale Handling
Products feature multilingual translatable name and description attributes. The application resolves the language locale dynamically by checking the incoming `Accept-Language` HTTP header:
* Send `Accept-Language: en` (or default fallback) to fetch English attributes.
* Send `Accept-Language: id` to fetch Indonesian (Bahasa Indonesia) translations.
* If an unsupported header value is sent, the system automatically falls back to the default English locale.

---

## 🚀 Installation & Local Setup

### Prerequisite: PostgreSQL Setup
Ensure you have a running PostgreSQL instance. The tests and development environment rely on row-level lock support (`FOR UPDATE`) which is fully supported by PostgreSQL.

1. **Clone & Configure Environment:**
   ```bash
   cp .env.example .env
   ```
   Update `.env` database parameters:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=online-store-api
   DB_USERNAME=postgres
   DB_PASSWORD=YOUR_PASSWORD
   ```

2. **Install Dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Migrate & Seed the Database:**
   ```bash
   php artisan migrate:fresh --seed
   ```

4. **Serve the Application:**
   Secure the local site using Herd or Valet:
   ```bash
   valet secure online-store-api
   # The site will now be hosted securely at https://online-store-api.test
   ```

---

## 🧪 Detailed Test Verification Guide

We use **Pest PHP** for our test suites. Standard integration tests are wrapped in database transactions to keep database state clean. However, the concurrency test is decoupled from transaction wrappers so that concurrent PHP-FPM web server processes can view and interact with the database tables.

### Running the Test Suites

#### 1. Run the Entire Test Suite
Executes all unit, integration, and high-concurrency tests:
```bash
php artisan test
```

#### 2. Run the Concurrency Test Independently
Runs only the race-condition test that simulates 30 concurrent order requests on a single product with 10 stock:
```bash
php artisan test --filter=concurrency
```

#### 3. Run Store Integration and CRUD Tests Only
Runs basic product catalog searches, localized translation listings, standard checkout validations, and full product/order CRUD checks:
```bash
php artisan test --filter=StoreApiTest
# and
php artisan test --filter=ProductApiTest
# and
php artisan test --filter=OrderApiTest
```

#### 4. Run Authentication & ACL Tests Only
Runs Sanctum logins, Fortify registrations, and Spatie permission authorization checks:
```bash
php artisan test --filter=AuthApiTest
# and
php artisan test --filter=PermissionApiTest
```

---

## 📝 API Documentation (Scramble) & Seeding

### 1. Interactive API Reference
This project utilizes **Dedoc Scramble** to auto-generate OpenAPI documentation.
- **Route**: Access the documentation locally at `/docs/api`.
- **Theme**: Scramble is styled to load in **Dark Mode** by default.
- **Favicon**: Features a custom storefront favicon brand at `/favicon.png`.

### 2. Database Seeding
You can populate the database with realistic test datasets (including products with English/Indonesian translations, regular/flash sale prices, and completed orders):
```bash
php artisan db:seed
```
This runs the default `DatabaseSeeder`, calling `StoreDatabaseSeeder` and `AclDatabaseSeeder`.

---

## 🔒 Concurrency Control & Database Locking Strategy

To prevent negative inventory stock values and overselling during a high-traffic flash sale, the checkout process operates under a strict database row-level locking strategy:

### The Serialization Workflow
1. **Open Database Transaction:** The order creation process in `CheckoutAction` runs completely inside a transaction block (`DB::transaction`).
2. **Pessimistic Locking (`lockForUpdate`):** For each product item in the payload, the query executes with a pessimistic lock:
   ```php
   $product = Product::where('id', $productId)->lockForUpdate()->first();
   ```
   This compiles to a `SELECT ... FOR UPDATE` query. Under the hood, PostgreSQL blocks any concurrent requests trying to read or write to the same product row until the lock is released.
3. **Inventory Sufficiency Verification:** The system verifies the stock:
   - If `stock < quantity`, it throws an `OutOfStockException` (reverts transaction, aborts checkout, and releases the row lock).
   - If `stock >= quantity`, it decrements the database value:
     ```php
     $product->decrement('stock', $quantity);
     ```
4. **Persist Order:** The order and snapshot order items are created.
5. **Transaction Commit:** The transaction is committed, making the stock decrement permanent and releasing the database row lock for the next waiting request.

### Database Engine Requirements
- **PostgreSQL / MySQL (InnoDB):** Must be used in local development and production. These engines support true row-level locking.
- **SQLite (In-Memory / File):** Does *not* support row-level locking. Under high write concurrency, SQLite will lock the entire database, leading to database lock errors rather than serialized queuing. Therefore, testing or deploying concurrent workloads against standard SQLite is unsupported.

---

## 🎮 Task 2: Hidden Item Game (Artisan Console Command)

We have implemented Task 2 as a Laravel Artisan Console Command `play:hidden-item`.

The game includes a **Cache-driven session system** where the player's coordinate `X` moves dynamically and the hidden item `$` remains persistent at its randomized hidden coordinate until player `X` lands on it.

### Game Grid Layout
The layout grid is represented as a 6x8 coordinate system (0-indexed):
- `#` represents an obstacle (non-walkable).
- `.` represents a clear path (walkable).
- `X` represents the current position of the player (starts at Row 4, Column 1).
- `$` represents the persistent location of the hidden item.

### Path Traversal & Order of Operations
The solver navigates the player from their *current* position `X` using the following sequence of steps:
1. **Up/North** `A >= 0` step(s).
2. **Right/East** `B >= 0` step(s).
3. **Down/South** `C >= 0` step(s).
4. **Left/West** `D >= 0` step(s).

All intermediate steps along each segment must consist of clear path points (`.`). If the player hits an obstacle (`#`) at any point along a segment, the path is blocked and invalid. A step count of `0` means the player does not move in that direction.

### Grid Solver Outcomes (from Starting position (4,1))
Running the solver on a fresh starting state outputs all unique coordinates reachable under these rules:
- `(Row 2, Col 5)`
- `(Row 2, Col 6)`
- `(Row 3, Col 5)`
- `(Row 4, Col 3)`
- `(Row 4, Col 5)`

On a fresh session start, the item `$` is randomly hidden at one of these 5 coordinates.

### How to Run the Game Command

#### 1. View Current Game Status
Displays the active game session state, player position, hidden item location, and renders the current grid map:
```bash
php artisan play:hidden-item
```

#### 2. Move the Player
Make a valid navigation move (relative to the current player position) using steps `A`, `B`, `C`, and `D`. Any omitted parameters default to `0`:
```bash
# Move Up 3, Right 5, Down 1, Left 1
php artisan play:hidden-item --A=3 --B=5 --C=1 --D=1

# Move Up 3 only (B, C, and D default to 0)
php artisan play:hidden-item --A=3
```
*Note: If the player successfully lands on `$`, they win the game and the session is cleared.*

#### 3. Reset the Game Session
Flush the active session cache and start a new game with a newly randomized hidden item:
```bash
php artisan play:hidden-item --reset
```

### Automated Testing
Run the command feature tests:
```bash
php artisan test --filter=PlayHiddenItemCommand
```
