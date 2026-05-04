# Floryn Garden — Mobile Application Architecture Guide

> **Purpose**: Complete architecture and workflow reference for building a dynamic customer-facing mobile application (React Native with JavaScript) that consumes the existing Floryn Garden Management System backend APIs.
>
> **Date**: February 27, 2026
>
> **Backend Stack**: Symfony 6 · Doctrine ORM · MySQL 8.0 · LexikJWTAuthenticationBundle · RS256

---

## Table of Contents

1. [API Endpoints the Mobile App Should Consume](#1-api-endpoints-the-mobile-app-should-consume)
2. [JWT Authentication Flow for Mobile](#2-jwt-authentication-flow-for-mobile)
3. [Complete API Request → Response Flow](#3-complete-api-request--response-flow)
4. [Safely Consuming APIs Without Affecting Admin/Staff](#4-safely-consuming-apis-without-affecting-adminstaff-operations)
5. [Recommended Mobile Client Architecture](#5-recommended-mobile-client-architecture)

---

## 1. API Endpoints the Mobile App Should Consume

### 1.1 Currently Available Endpoints (JWT-Protected, `/api` Prefix)

| Method   | Endpoint            | Response Shape                                                                 | Auth         |
|----------|---------------------|--------------------------------------------------------------------------------|--------------|
| **POST** | `/api/login`        | `{ token: "eyJ..." }`                                                         | Public       |
| **GET**  | `/api/me`           | `{ id, username, roles }`                                                      | Bearer JWT   |
| **GET**  | `/api/flowers`      | `{ flowers: [...], total }`                                                    | Bearer JWT   |
| **GET**  | `/api/customers`    | `{ customers: [...], total }`                                                  | Bearer JWT   |
| **GET**  | `/api/reservations` | `{ reservations: [...], total }`                                               | Bearer JWT   |
| **GET**  | `/api/dashboard`    | `{ totalFlowers, totalCustomers, totalReservations, lowStockFlowers }`          | Bearer JWT   |

Each flower object in `/api/flowers` includes: `id`, `name`, `category`, `price`, `discountPrice`, `stockQuantity`, `freshnessStatus`, `status`, `dateReceived`, `expiryDate`, and the `supplier` name.

### 1.2 Current Limitation — These APIs Are Staff-Oriented

Every existing endpoint returns **all records unfiltered** and requires `IS_AUTHENTICATED_FULLY` — meaning any JWT user (currently only `ROLE_STAFF` and above). There is no `ROLE_CUSTOMER` role, no customer login, and no customer-scoped filtering.

### 1.3 What a Customer Mobile App Actually Needs (Future Endpoints)

| Feature           | Proposed Endpoint                   | Method | Purpose                                         |
|-------------------|-------------------------------------|--------|--------------------------------------------------|
| **Auth**          | `/api/customer/register`            | POST   | Customer self-registration                       |
|                   | `/api/customer/login`               | POST   | Customer JWT login                               |
|                   | `/api/customer/me`                  | GET    | View own profile                                 |
|                   | `/api/customer/me`                  | PUT    | Update own profile                               |
| **Catalog**       | `/api/customer/flowers`             | GET    | Browse available flowers (status=Available only)  |
|                   | `/api/customer/flowers/{id}`        | GET    | Single flower detail                             |
|                   | `/api/customer/flowers?category=X`  | GET    | Filter by category                               |
| **Orders**        | `/api/customer/reservations`        | POST   | Create reservation (with items)                  |
|                   | `/api/customer/reservations`        | GET    | View own reservations only                       |
|                   | `/api/customer/reservations/{id}`   | GET    | Single reservation + details                     |
|                   | `/api/customer/reservations/{id}/cancel` | POST | Cancel own reservation                      |
| **Payments**      | `/api/customer/payments`            | GET    | View own payment history                         |
|                   | `/api/customer/payments/{id}`       | GET    | Single payment detail                            |
| **Notifications** | `/api/customer/notifications`       | GET    | Own notification history                         |

### 1.4 What You Can Do Right Now (No Backend Changes)

Using the existing 6 endpoints with a staff JWT account:

| Feature              | How                                                                      | Endpoint              |
|----------------------|--------------------------------------------------------------------------|-----------------------|
| Login                | Staff credentials via JWT                                                | `POST /api/login`     |
| Browse flowers       | Fetch all, filter client-side for `status == "Available"`                | `GET /api/flowers`    |
| View categories      | Group client-side by `category` field                                    | `GET /api/flowers`    |
| See discounts        | Show `discountPrice` when non-null (auto-applied for "Last Sale" items)  | `GET /api/flowers`    |
| View reservations    | Fetch all, filter client-side for a specific customer                    | `GET /api/reservations` |
| Dashboard stats      | Display totals directly                                                  | `GET /api/dashboard`  |

> **Trade-off**: This works as a read-only prototype, but the app sees all customers/reservations (not scoped). For production, customer-scoped endpoints are essential.

---

## 2. JWT Authentication Flow for Mobile

### 2.1 Backend JWT Configuration

| Setting    | Value                                                        |
|------------|--------------------------------------------------------------|
| Algorithm  | RS256 (asymmetric, public/private PEM keys)                  |
| Token TTL  | **3600 seconds (1 hour)**                                    |
| Keys       | `config/jwt/private.pem` (signing) / `config/jwt/public.pem` (verification) |
| Refresh    | **Not configured** — no refresh endpoint exists              |

The API uses two dedicated firewalls:

```
api_login firewall (^/api/login) → stateless, json_login authenticator
api firewall       (^/api)       → stateless, jwt authenticator
```

Both are **stateless** (no PHP sessions, no cookies).

### 2.2 Complete Login Flow

```
┌──────────────────┐                           ┌──────────────────────┐
│   Mobile App     │                           │   Symfony Backend     │
│                  │                           │                      │
│  Login Screen    │   POST /api/login         │  api_login firewall  │
│  ─────────────── │ ────────────────────────> │  json_login handler  │
│  username: admin │   Content-Type:           │                      │
│  password: ****  │    application/json       │  1. Find user by     │
│                  │   Body:                   │     username (DB)    │
│                  │   {                       │  2. Verify password  │
│                  │     "username":"admin",   │     (bcrypt/argon2)  │
│                  │     "password":"pass123"  │  3. Generate JWT     │
│                  │   }                       │     signed with      │
│                  │                           │     private.pem      │
│                  │   200 OK                  │  4. Set exp = now    │
│  Store token     │ <──────────────────────── │     + 3600           │
│  securely        │   {"token":"eyJhbGc..."} │                      │
└──────────────────┘                           └──────────────────────┘
```

### 2.3 Token Storage — Secure Storage (React Native)

| Platform              | Secure Storage                   | Library                                          |
|-----------------------|----------------------------------|--------------------------------------------------|
| **React Native (JS)** | Platform-bridged secure store    | `react-native-keychain` or `expo-secure-store`   |

**Never** store JWT in plain `AsyncStorage`. Always use a secure storage library that leverages the platform's native keychain/keystore under the hood.

### 2.4 Authenticated Request Pattern

```
Every subsequent API call:

  GET /api/flowers HTTP/1.1
  Host: your-server.com
  Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
  Content-Type: application/json
  Accept: application/json
```

### 2.5 Token Lifecycle Management (No Refresh Token)

Since the backend has **no refresh token endpoint**, the mobile app must handle expiry explicitly:

```
App Launch
    │
    ▼
┌─────────────────────────┐     ┌──────────────────┐
│ Read token from secure  │────>│ Token exists?     │
│ storage                 │     └────────┬─────────┘
└─────────────────────────┘          Yes │    No
                                        ▼     │
                              ┌──────────────┐ │
                              │ Decode JWT   │ │
                              │ payload      │ │
                              │ (base64, no  │ │
                              │  verify)     │ │
                              │ Check `exp`  │ │
                              └──────┬───────┘ │
                                     │         │
                               Valid │ Expired │
                                     ▼         ▼
                              ┌────────┐  ┌──────────┐
                              │ Home   │  │ Login    │
                              │ Screen │  │ Screen   │
                              └────────┘  └──────────┘

During API Calls:
  - If server returns 401 → token expired server-side
  - Clear stored token immediately
  - Redirect to Login Screen
  - Optionally: store credentials encrypted for silent re-login
```

### 2.6 Logout Flow

```
User taps "Logout"
    │
    ├── 1. Clear JWT from secure storage
    ├── 2. Clear all cached data / state
    ├── 3. Navigate to Login Screen
    │
    (No server call needed — JWT is stateless,
     the backend has no token blacklist)
```

---

## 3. Complete API Request → Response Flow

### 3.1 End-to-End Diagram: `GET /api/flowers`

```
┌─────────────┐
│ MOBILE APP  │
│             │
│ FlowerScreen│
│  onMount()  │──────────────────────────────────────────────────┐
│             │                                                  │
└─────────────┘                                                  │
                                                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ NETWORK LAYER (HTTPS)                                                   │
│                                                                         │
│  GET https://floryngarden.com/api/flowers                               │
│  Headers:                                                               │
│    Authorization: Bearer eyJhbGciOiJSUzI1NiIs...                       │
│    Accept: application/json                                             │
└──────────────────────────────┬──────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ SYMFONY ROUTER                                                            │
│   URL matches pattern ^/api → enters "api" firewall                       │
│   Route resolved: api_flowers (ApiController::flowers)                    │
└──────────────────────────────┬───────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ JWT FIREWALL (stateless: true, jwt: ~)                                    │
│                                                                           │
│  1. Extract token from "Authorization: Bearer ..." header                 │
│  2. Decode JWT header + payload (base64)                                  │
│  3. Verify RS256 signature using config/jwt/public.pem                    │
│  4. Check `exp` claim — reject if expired (→ 401 "Expired JWT Token")    │
│  5. Extract `username` claim from payload                                 │
│  6. Load User entity via UserProvider (SELECT * FROM user WHERE           │
│     username = ?)                                                         │
│  7. Inject authenticated User token into security context                 │
│                                                                           │
│  FAIL conditions:                                                         │
│    - No header / malformed → 401 "JWT Token not found"                   │
│    - Invalid signature     → 401 "Invalid JWT Token"                     │
│    - Expired               → 401 "Expired JWT Token"                     │
│    - User not found        → 401 "Invalid credentials"                   │
└──────────────────────────────┬───────────────────────────────────────────┘
                               │ ✓ Authenticated
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ ACCESS CONTROL                                                            │
│   Rule: ^/api → IS_AUTHENTICATED_FULLY                                    │
│   User has valid token → ✓ GRANTED                                        │
└──────────────────────────────┬───────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ ApiController::flowers()                                                  │
│                                                                           │
│   $flowers = $this->flowerRepository->findAll();                         │
│                                                                           │
│   foreach ($flowers as $flower) {                                        │
│       $data[] = [                                                        │
│           'id'              => $flower->getId(),                          │
│           'name'            => $flower->getName(),                       │
│           'category'        => $flower->getCategory(),                   │
│           'price'           => $flower->getPrice(),                      │
│           'discountPrice'   => $flower->getDiscountPrice(),              │
│           'stockQuantity'   => $flower->getStockQuantity(),              │
│           'freshnessStatus' => $flower->getFreshnessStatus(),            │
│           'status'          => $flower->getStatus(),                     │
│           'dateReceived'    => $flower->getDateReceived()->format('...'),│
│           'expiryDate'      => $flower->getExpiryDate()->format('...'), │
│           'supplier'        => $flower->getSupplier()?->getSupplierName()│
│       ];                                                                 │
│   }                                                                      │
│                                                                           │
│   return new JsonResponse(['flowers' => $data, 'total' => count(...)]);  │
└──────────────────────────────┬───────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ DOCTRINE ORM → MySQL 8.0 (port 3309)                                     │
│                                                                           │
│   SELECT f.*, s.supplier_name                                             │
│   FROM flower f                                                           │
│   LEFT JOIN supplier s ON f.supplier_id = s.id                            │
│                                                                           │
│   (FlowerBatch data is lazy-loaded only if accessed)                      │
└──────────────────────────────┬───────────────────────────────────────────┘
                               │ Result set
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ HTTP RESPONSE                                                             │
│                                                                           │
│   HTTP/1.1 200 OK                                                         │
│   Content-Type: application/json                                          │
│                                                                           │
│   {                                                                       │
│     "flowers": [                                                          │
│       {                                                                   │
│         "id": 1,                                                          │
│         "name": "Red Rose Bouquet",                                       │
│         "category": "Bouquet Flowers",                                    │
│         "price": 450.00,                                                  │
│         "discountPrice": null,                                            │
│         "stockQuantity": 25,                                              │
│         "freshnessStatus": "Fresh",                                       │
│         "status": "Available",                                            │
│         "dateReceived": "2026-02-25",                                     │
│         "expiryDate": "2026-03-10",                                       │
│         "supplier": "Sunflower Farms"                                     │
│       }                                                                   │
│     ],                                                                    │
│     "total": 42                                                           │
│   }                                                                       │
└──────────────────────────────┬───────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ MOBILE APP                                                                │
│                                                                           │
│  1. HTTP client receives JSON response                                    │
│  2. Deserialize into List of Flower model objects                         │
│  3. Apply client-side filter: status == "Available"                       │
│  4. Update state (ViewModel / Provider / Context)                         │
│  5. UI reactively rebuilds flower grid/list                               │
└──────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Error Response Handling Matrix

| HTTP Code       | Cause                         | Backend Behavior                          | Mobile App Action                    |
|-----------------|-------------------------------|------------------------------------------|--------------------------------------|
| **200**         | Success                       | JSON response body                       | Parse and display                    |
| **401**         | Missing/invalid/expired JWT   | `{"code":401,"message":"..."}`           | Clear token → Login Screen           |
| **403**         | Insufficient role             | `{"code":403,"message":"Access Denied"}` | Show error, don't retry              |
| **404**         | Route not found               | HTML error page or JSON                  | Show "not found" message             |
| **500**         | Server error                  | Exception details (dev) or generic (prod)| Show retry option                    |
| **0 / timeout** | Network failure               | No response                              | Show offline banner, retry with backoff |

---

## 4. Safely Consuming APIs Without Affecting Admin/Staff Operations

### 4.1 Firewall Separation (The Primary Safety Mechanism)

Traffic is routed to **completely separate firewalls**:

```
Incoming Request
       │
       ├── URL matches ^/api/login  → api_login firewall (stateless, json_login)
       ├── URL matches ^/api        → api firewall       (stateless, jwt)
       └── Everything else          → main firewall       (session, form login, OAuth)
```

| Property              | API Firewall (Mobile)               | Main Firewall (Web Admin)                |
|-----------------------|-------------------------------------|------------------------------------------|
| State                 | **Stateless** — no PHP session      | Session-based (PHPSESSID cookie)         |
| Auth method           | JWT Bearer header                   | Form login + CSRF token / Google OAuth   |
| Token storage         | Client-side only                    | Server-side session file/cache           |
| Remember-me           | Not applicable                      | 7-day cookie                             |
| Can cross-authenticate? | **No** — JWT cannot create a web session | **No** — web session ignored by API firewall |

**This means**: A mobile JWT request physically cannot impersonate a web admin session, and vice versa.

### 4.2 Read-Only API Surface

All 4 data endpoints (`/api/flowers`, `/api/customers`, `/api/reservations`, `/api/dashboard`) are **GET-only**. There are zero POST/PUT/DELETE operations under `/api` (except login). The mobile app **cannot write data** through these endpoints.

### 4.3 CSRF Protection on Web Mutations

All web write operations (create, edit, delete) require CSRF tokens generated server-side inside Twig templates. Even if a mobile client somehow crafted a POST to `/flower/{id}`, it would fail CSRF validation.

### 4.4 Best Practices for the Mobile Client

| Practice                                         | Why                                                                          |
|--------------------------------------------------|------------------------------------------------------------------------------|
| **Never store or transmit web session cookies**   | Prevents accidental cross-firewall leakage                                   |
| **Always use `Authorization: Bearer` header**     | This is the only auth mechanism the API firewall accepts                     |
| **Implement request timeouts (10–15 seconds)**    | Prevents hanging connections from degrading the server                       |
| **Add exponential backoff on retries**            | Avoids hammering the backend on transient failures                           |
| **Enforce HTTPS only**                            | JWT is a bearer token — whoever intercepts it gets full access               |
| **Don't cache sensitive data unencrypted**        | Reservation/customer data should be in-memory or encrypted storage           |
| **Log API errors, never log tokens**              | Essential for debugging, dangerous if tokens leak into logs                  |
| **Set `Accept: application/json` header**         | Ensures Symfony returns JSON errors, not HTML error pages                    |

### 4.5 Rate Limiting Consideration

The backend currently has **no rate limiting** on `/api` endpoints. For production, consider adding `nelmio/api-rate-limit-bundle` or Symfony's built-in `#[RateLimiter]` to prevent abuse (e.g., 60 requests/minute per token).

---

## 5. Recommended Mobile Client Architecture

### 5.1 React Native Project Structure (JavaScript)

```
src/
├── config/
│   └── api.js                     # Base URL, timeouts, constants
│
├── models/                        # Helper functions & constants matching API shapes
│   ├── Flower.js                  #   { id, name, category, price, discountPrice,
│   │                              #     stockQuantity, freshnessStatus, status,
│   │                              #     dateReceived, expiryDate, supplier }
│   ├── Customer.js                #   { id, fullName, phone, email, address,
│   │                              #     dateRegistered, reservationCount }
│   ├── Reservation.js             #   { id, customerName, pickupDate, totalAmount,
│   │                              #     paymentStatus, reservationStatus, dateReserved }
│   └── DashboardStats.js          #   { totalFlowers, totalCustomers,
│                                  #     totalReservations, lowStockFlowers }
│
├── services/                      # API communication (one service per domain)
│   ├── apiClient.js               #   Axios instance with JWT interceptor
│   ├── authService.js             #   login(), logout(), getToken(), isAuthenticated()
│   ├── flowerService.js           #   getFlowers(), getFlowerById() (client filter)
│   ├── customerService.js         #   getCustomers()
│   ├── reservationService.js      #   getReservations()
│   └── dashboardService.js        #   getDashboardStats()
│
├── state/                         # State management (Context API / Zustand / Redux)
│   ├── AuthContext.js             #   token, user, isAuthenticated, login(), logout()
│   ├── useFlowers.js              #   flowers[], loading, error, refresh()
│   ├── useReservations.js         #   reservations[], filter by status
│   └── useDashboard.js            #   stats object
│
├── navigation/
│   ├── AppNavigator.js            #   Root navigator (auth check)
│   ├── AuthStack.js               #   Login screens
│   └── MainTabs.js                #   Home, Flowers, Reservations, Profile
│
├── screens/
│   ├── auth/
│   │   └── LoginScreen.js
│   ├── home/
│   │   └── HomeScreen.js          #   Dashboard stats overview
│   ├── flowers/
│   │   ├── FlowerCatalogScreen.js #   Grid/list of available flowers
│   │   └── FlowerDetailScreen.js  #   Single flower view
│   ├── reservations/
│   │   └── ReservationsScreen.js  #   Reservation list with status filter
│   └── profile/
│       └── ProfileScreen.js       #   User info from /api/me
│
├── components/                    # Reusable UI components
│   ├── FlowerCard.js              #   Flower preview tile
│   ├── StatusBadge.js             #   Fresh/Good/Last Sale/Expired chips
│   ├── PriceBadge.js              #   ₱450 or ₱450 → ₱360 (discount)
│   ├── LoadingSpinner.js
│   ├── ErrorRetry.js              #   "Something went wrong" + retry button
│   └── EmptyState.js
│
└── utils/
    ├── tokenStorage.js            #   Secure read/write/delete (react-native-keychain)
    ├── formatCurrency.js          #   (value) => `₱${value.toLocaleString()}`
    └── freshnessColor.js          #   Status → color mapping
```



### 5.3 Key Implementation Examples (JavaScript / React Native)

#### API Client with JWT Interceptor — `services/apiClient.js`

```js
import axios from 'axios';
import { getToken, clearToken } from '../utils/tokenStorage';

const api = axios.create({
  baseURL: 'https://your-domain.com/api',
  timeout: 15000,
  headers: { 'Accept': 'application/json' },
});

// Attach JWT to every request
api.interceptors.request.use(async (config) => {
  const token = await getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Handle 401 globally
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await clearToken();
      // navigate to login screen
    }
    return Promise.reject(error);
  }
);

export default api;
```

#### Auth Context — `state/AuthContext.js`

```js
import React, { createContext, useState, useContext, useEffect } from 'react';
import { login as apiLogin } from '../services/authService';
import { getToken, saveToken, clearToken } from '../utils/tokenStorage';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [token, setToken] = useState(null);
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getToken().then((stored) => {
      if (stored) setToken(stored);
      setLoading(false);
    });
  }, []);

  const login = async (username, password) => {
    const { token } = await apiLogin(username, password);
    await saveToken(token);
    setToken(token);
  };

  const logout = async () => {
    await clearToken();
    setToken(null);
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ token, user, loading, login, logout, isAuthenticated: !!token }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
```

#### Flower Model Helpers — `models/Flower.js`

```js
// Flower categories (matches backend Choice constraint)
export const FLOWER_CATEGORIES = [
  'Bouquet Flowers', 'Tropical Flowers', 'Wedding Flowers',
  'Funeral Flowers', 'Seasonal Flowers', 'Potted Plants',
  'Garden Flowers', 'Exotic Flowers', 'Indoor Plants',
  'Decorative Plants',
];

// Freshness status → color mapping
export const FRESHNESS_COLORS = {
  'Fresh':     '#22c55e',  // green
  'Good':      '#3b82f6',  // blue
  'Last Sale': '#f97316',  // orange
  'Expired':   '#ef4444',  // red
};

// Payment methods (matches backend Choice constraint)
export const PAYMENT_METHODS = ['Cash', 'PayPal'];

export const getEffectivePrice = (flower) =>
  flower.discountPrice ?? flower.price;

export const isLowStock = (flower) =>
  flower.stockQuantity > 0 && flower.stockQuantity < 5;
```

#### Custom Hook — `state/useFlowers.js`

```js
import { useState, useEffect, useCallback } from 'react';
import { getFlowers } from '../services/flowerService';

export const useFlowers = () => {
  const [flowers, setFlowers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchFlowers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getFlowers();
      // Client-side filter: only show available flowers
      setFlowers(data.filter((f) => f.status === 'Available'));
    } catch (err) {
      setError(err.message || 'Failed to load flowers');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchFlowers(); }, [fetchFlowers]);

  return { flowers, loading, error, refresh: fetchFlowers };
};
```

### 5.4 State Management Flow

```
User Action → Service Call → API Client → HTTP Request
                                              ↓
State Update ← Parse Models ← JSON Response ←┘
     ↓
UI Rebuilds (reactive)
```

### 5.5 Client-Side Business Logic

Since the API returns **all records**, implement these filters and display logic on the mobile side:

| Task                        | Implementation                                                                                  |
|-----------------------------|-------------------------------------------------------------------------------------------------|
| **Show only available**     | `flowers.filter(f => f.status === 'Available')`                                                 |
| **Display discount pricing**| If `discountPrice != null`, show strikethrough on `price` and highlight `discountPrice`          |
| **Category tabs/filters**   | Group by `category` — 10 possible values                                                        |
| **Freshness badges**        | Map `freshnessStatus` → color: Fresh=green, Good=blue, Last Sale=orange, Expired=red            |
| **Currency**                | Philippine Peso: `₱` prefix, two decimal places                                                |
| **Low stock indicator**     | `stockQuantity < 5` → show "Low Stock" badge                                                   |
| **Sort flowers**            | By price, name, freshness, or expiry date — all sortable client-side                            |
| **Phone format**            | Display as `+63 XXX XXX XXXX` (backend stores `+63XXXXXXXXXX`)                                 |

---

## Summary

| Aspect       | Current State                                                    | Production-Ready Needs                              |
|--------------|------------------------------------------------------------------|-----------------------------------------------------|
| **Auth**     | Working — `POST /api/login` → JWT (RS256, 1h TTL)               | Add refresh tokens or extend TTL                    |
| **Read APIs**| 4 data endpoints + user info — returns all records               | Add customer-scoped endpoints                       |
| **Write APIs**| None under `/api` — mobile is read-only                         | Add reservation creation, payment                   |
| **Isolation**| Firewalls fully separated — JWT ≠ web session                   | Already production-ready                            |
| **Mobile**   | N/A                                                              | Use the structure above (service → state → UI)      |

The mobile app can be built **today** as a **read-only catalog and reservation viewer** using the existing 6 endpoints. For full customer self-service (placing orders, making payments), the backend would need the customer-scoped API endpoints outlined in Section 1.3.
