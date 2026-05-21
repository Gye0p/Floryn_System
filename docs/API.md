# Floryn Garden — Customer & Auth API Reference

Base URL (production): `https://florynsystem-production.up.railway.app`

All JSON requests use `Content-Type: application/json` and `Accept: application/json`.

---

## Authentication

### Login (JWT access token + refresh token)

`POST /api/login`

```json
{ "username": "jane_doe", "password": "secret123" }
```

**200**

```json
{
  "token": "eyJ...",
  "refresh_token": "a1b2c3...",
  "expires_at": "2026-06-21T12:00:00+00:00"
}
```

Access token TTL: **1 hour** (3600s). Refresh token TTL: **30 days** (configurable via `REFRESH_TOKEN_TTL`).

### Refresh access token

`POST /api/token/refresh` (public)

```json
{ "refresh_token": "a1b2c3..." }
```

**200** — returns new `token` and rotated `refresh_token`.

### Google / Firebase login

`POST /api/firebase-login`

```json
{ "firebase_token": "<Google ID token>" }
```

Returns `token`, `refresh_token`, and `user` object.

### Register

`POST /api/register` — body: `username`, `password`, `email`, `full_name`, optional `phone`, `address`.

`POST /api/register/google` — body: `firebase_token`, optional profile fields.

### Check approval

`POST /api/check-approval` — `{ "username": "..." }`

### Password reset

- `POST /api/password-reset/request` — `{ "email": "..." }`
- `POST /api/password-reset/confirm` — `{ "token": "...", "password": "..." }`

### Authenticated profile (any role)

`GET /api/me` — `Authorization: Bearer <access_token>`

---

## Customer API (`ROLE_CUSTOMER` required)

Header: `Authorization: Bearer <access_token>`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/customer/me` | Own profile |
| PUT | `/api/customer/me` | Update `full_name`, `phone`, `address` |
| GET | `/api/customer/flowers` | Catalog (`?category=`, `?search=`) |
| GET | `/api/customer/flowers/{id}` | Flower detail |
| GET | `/api/customer/categories` | Category list |
| GET | `/api/customer/bouquets` | Ready bouquets |
| GET | `/api/customer/bouquets/{id}` | Bouquet detail |
| GET | `/api/customer/reservations` | Own reservations |
| POST | `/api/customer/reservations` | Create reservation |
| GET | `/api/customer/reservations/{id}` | Reservation detail |
| POST | `/api/customer/reservations/{id}/cancel` | Cancel pending/confirmed |
| GET | `/api/customer/payments` | Own payment history |
| GET | `/api/customer/payments/{id}` | Payment detail |
| GET | `/api/customer/notifications` | Notification log |
| POST | `/api/customer/fcm-token` | Register FCM device token |
| GET | `/api/customer/mercure-token` | SSE subscriber JWT + hub URL |

### Create reservation

`POST /api/customer/reservations`

```json
{
  "pickupDate": "2026-05-25",
  "items": [
    { "flowerId": 1, "quantity": 2 }
  ]
}
```

**201** — `{ "message", "reservation": { ... } }`

### Cancel reservation

`POST /api/customer/reservations/{id}/cancel`

Allowed when status is `Pending` or `Confirmed` and payment is not `Paid`.

---

## Staff API (`ROLE_STAFF` required)

| Method | Endpoint |
|--------|----------|
| GET | `/api/flowers` |
| GET | `/api/customers` |
| GET | `/api/reservations` |
| GET | `/api/dashboard` |

Customers **cannot** access these routes (403).

---

## Error format

```json
{
  "error": "Human-readable message",
  "details": { "field": "Validation message" }
}
```

| Code | Meaning |
|------|---------|
| 400 | Validation / business rule |
| 401 | Missing or invalid JWT / refresh token |
| 403 | Wrong role or not approved |
| 404 | Resource not found |
| 409 | Duplicate registration |
| 500 | Server error |

---

## Real-time (Mercure)

1. `GET /api/customer/mercure-token` → `{ token, hub_url, topic }`
2. Connect SSE: `hub_url?topic=<topic>` with header `Authorization: Bearer <subscriber JWT>`
3. Events: `reservation_created`, `reservation_updated` with `reservation_id`, `status`

See [MOBILE_SYNC_GUIDE.md](MOBILE_SYNC_GUIDE.md) for full mobile ↔ web flow.
