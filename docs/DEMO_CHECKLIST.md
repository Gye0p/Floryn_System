# Final Project Demo Checklist

Use this during your presentation to hit all rubric areas.

## Before class

- [ ] Railway backend URL loads in browser
- [ ] Mobile app built with correct `BASE_URL` in `config.ts`
- [ ] Test customer account **approved** by admin
- [ ] Mercure + MySQL running (or production equivalents)
- [ ] Phone/emulator charged; screen mirroring ready

## Live demo flow

### 1. Mobile integration (15 pts)

- [ ] Login (email or Google)
- [ ] Browse flower catalog with search/category
- [ ] View bouquet catalog (optional)
- [ ] Create reservation with pickup date
- [ ] Open reservations list — show status + payment chip

### 2. Customer API (15 pts)

- [ ] Mention 5+ endpoints: profile, flowers, reservations, payments, cancel
- [ ] Show Postman or README `docs/API.md` sample request/response

### 3. Auth & security (15 pts)

- [ ] JWT in `Authorization: Bearer` header
- [ ] Explain admin approval gate for new customers
- [ ] Show refresh token flow (`POST /api/token/refresh`)
- [ ] Passwords hashed server-side (no plain text)

### 4. RBAC (10 pts)

- [ ] Customer cannot access staff URLs (`/api/flowers` → 403)
- [ ] Web admin requires staff login
- [ ] Admin-only user management

### 5. Sync (10 pts)

- [ ] Mobile order appears in web dashboard
- [ ] Staff changes status → mobile updates (Mercure “Live” or FCM)
- [ ] Cancel on mobile → reflected on web

### 6. Database (10 pts)

- [ ] Explain entities: User, Flower, Reservation, Payment
- [ ] Stock restored on cancel

### 7. Errors (10 pts)

- [ ] Show validation error (e.g. empty cart or bad pickup date)
- [ ] Friendly message in app

### 8. UI/UX (5 pts)

- [ ] Consistent Floryn branding on web + mobile

### 9. Deployment (5 pts)

- [ ] Railway URL + Docker local setup in README

### 10. Documentation (5 pts)

- [ ] `docs/API.md`, `docs/MOBILE_SYNC_GUIDE.md`, README install steps

## Backup if network fails

- Screen recording of full flow
- Postman collection exported
- phpMyAdmin screenshot of reservation row
