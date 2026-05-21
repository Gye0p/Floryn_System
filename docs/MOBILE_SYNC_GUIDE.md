# Mobile ↔ Web Synchronization Guide

This guide explains how the **Floryn mobile app** (`C:\Floryn_app`) stays in sync with the **Symfony web admin** (`WEBDEVSYSTEM`).

---

## Architecture

```
┌─────────────────┐     HTTPS REST      ┌──────────────────────┐
│  React Native   │ ◄─────────────────► │  Symfony API         │
│  (Floryn_app)   │   JWT Bearer        │  /api/customer/*     │
└────────┬────────┘                     └──────────┬───────────┘
         │                                         │
         │ Mercure SSE                             │ Doctrine ORM
         │ FCM push                                ▼
         ▼                               ┌──────────────────────┐
┌─────────────────┐                     │  MySQL (shared DB)   │
│ Mercure Hub     │ ◄── publish ────────│  Web admin Twig UI   │
└─────────────────┘                     └──────────────────────┘
```

Both clients read/write the **same database**. There is no separate mobile database.

---

## Data flows

### 1. Customer places order (mobile → web)

1. Mobile: `POST /api/customer/reservations` with flower items.
2. Backend: transaction, stock deduction, reservation `Pending` / `Unpaid`.
3. Backend: Mercure publish on topic `/reservations/{userId}`.
4. Web admin: reservation appears on **Reservations** list immediately (refresh or Turbo).

### 2. Staff updates order (web → mobile)

1. Staff edits reservation in web admin (`ReservationController`).
2. Backend: saves status, sends **FCM** (app closed) and **Mercure** (app open).
3. Mobile `websocketService` receives event → `ReservationsScreen` refetches list.

### 3. Customer cancels order (mobile → web)

1. Mobile: `POST /api/customer/reservations/{id}/cancel`.
2. Backend: restores stock, sets status `Cancelled`, notifies Mercure + FCM.
3. Web admin: shows `Cancelled` on next page load.

---

## Setup checklist

### Backend (local)

```bash
docker-compose up -d          # MySQL + Mercure on :3000
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair
symfony serve                 # http://localhost:8000
```

`.env.local`:

```env
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!
CORS_ALLOW_ORIGIN=^https?://.*
```

### Mobile app

In `C:\Floryn_app\src\app\api\config.ts`, set `BASE_URL` to your backend:

- Local Android emulator: `http://10.0.2.2:8000`
- Physical device on LAN: `http://<your-pc-ip>:8000`
- Production: `https://florynsystem-production.up.railway.app`

Rebuild the app after changing `BASE_URL`.

### Production (Railway)

- Deploy backend with `DATABASE_URL`, JWT keys, `MERCURE_*` env vars.
- Mercure must be reachable from mobile devices (public URL in `MERCURE_PUBLIC_URL`).
- Firebase: FCM server key configured in `FcmNotificationService` (if used).

---

## Mobile implementation map

| Feature | File |
|---------|------|
| API calls | `src/app/api/customerApi.ts` |
| JWT + refresh | `src/app/api/client.ts`, `src/utils/tokenStorage.ts` |
| Mercure SSE | `src/services/websocketService.ts` |
| FCM | `src/services/notificationService.ts` |
| Start services after login | `App.tsx` → `PostLoginServices` |

### After login (customer)

1. Save `token` + `refresh_token` to secure storage.
2. `notificationService.init()` → `POST /api/customer/fcm-token`.
3. `websocketService.connect()` → `GET /api/customer/mercure-token` → EventSource.

### Token refresh

When any API returns **401**, call `POST /api/token/refresh` with stored `refresh_token`, save new tokens, retry once.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `401 JWT Token not found` on Railway | Ensure `public/.htaccess` forwards `Authorization` (fixed in v1.5). |
| Mercure never connects | Check hub URL, subscriber JWT, firewall allows `:3000` or public Mercure URL. |
| FCM not received | Verify `fcm-token` registered, Firebase config in app, backend FCM credentials. |
| Web does not show mobile order | Confirm same `DATABASE_URL`; run migrations; check customer is approved. |
| Customer sees all shop data | Old bug — staff `/api/*` now requires `ROLE_STAFF`. Update mobile to customer endpoints only. |

---

## Demo script (2–3 minutes)

1. Register customer on mobile → pending approval.
2. Admin approves user in web → **User Management**.
3. Mobile login → browse flowers → create reservation.
4. Web **Reservations** → confirm order.
5. Mobile list updates (Live indicator / pull to refresh).
6. Mobile cancel a pending order → web shows Cancelled + stock restored.
