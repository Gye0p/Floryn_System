# Changelog

All notable changes to the Floryn Garden Flower Shop Management System are documented here.

This project follows [Semantic Versioning](https://semver.org/) and [Conventional Commits](https://www.conventionalcommits.org/).

---

## [Unreleased]

### Added
- Customer API: `GET /api/customer/payments`, `GET /api/customer/payments/{id}`
- Customer API: `POST /api/customer/reservations/{id}/cancel` (stock restore)
- Customer API: `GET /api/customer/bouquets`, `GET /api/customer/bouquets/{id}`
- JWT refresh tokens: `POST /api/token/refresh`, `RefreshToken` entity, 30-day TTL (`REFRESH_TOKEN_TTL`)
- Documentation: `docs/API.md`, `docs/MOBILE_SYNC_GUIDE.md`, `docs/DEMO_CHECKLIST.md`

### Changed
- Staff dashboard API (`/api/flowers`, `/api/customers`, etc.) now requires `ROLE_STAFF` (customers cannot read all shop data)
- `ApiFirebaseLogin` returns `refresh_token` alongside JWT

### Fixed
- Apache on Railway not forwarding `Authorization: Bearer` to PHP, causing `401 JWT Token not found` on all authenticated API routes while `/api/login` still worked
- Added `public/.htaccess` rewrite rule and Dockerfile `SetEnvIf` for `HTTP_AUTHORIZATION`

---

## [1.5.0] – 2026-05-19

### Added
- `FcmNotificationService` for Firebase Cloud Messaging push notifications to mobile clients
- `GoogleIdTokenVerifier` service for secure server-side Google ID token validation
- `WebSocketNotifier` service abstraction for real-time event broadcasting
- `mercure.yaml` package configuration for Symfony Mercure bundle
- `compose.override.yaml` for local Docker Compose overrides
- Uploads directory placeholder (`public/uploads/flowers/.gitkeep`)

### Changed
- `ApiRegistrationController` updated to correctly handle email-based registration lifecycle
- `ApiCustomerController` improved response structure and error handling
- `ApiFirebaseLoginController` refactored to use `GoogleIdTokenVerifier` service
- `ReservationController` updated status workflow logic
- `User` entity updated with additional approval and verification fields
- `UserRepository` query methods refined for performance
- `docker-compose.yml` updated with Mercure network configuration
- `security.yaml` access control rules updated for API routes
- `config/bundles.php` registered new bundles

### Fixed
- Pending admin approval loop not resolving after account approval
- Email registration returning 409 conflict on re-registration attempts
- JWT authentication failing for approved users

---

## [1.4.0] – 2026-05-16

### Added
- Railway deployment configuration via Dockerfile
- Production entrypoint shell script with migration auto-run
- `.gitattributes` enforcing LF line endings for shell scripts

### Fixed
- CRLF line-ending error in Docker entrypoint causing `no such file or directory` on Linux containers
- `DATABASE_URL` misconfiguration between local Docker and Railway production environments

---

## [1.3.0] – 2026-05-10

### Added
- Bouquet management module (`Bouquet`, `BouquetItem` entities)
- Bouquet CRUD controller and Twig templates
- Storefront UI for customer-facing product catalog
- Sales flow improvements in POS system
- `Version20260510180707` migration for bouquet schema

---

## [1.2.0] – 2026-03-11

### Added
- User authentication system with email/password login
- Google OAuth integration via KnpU OAuth2 bundle
- JWT token authentication for REST API (`LexikJWT`)
- Email verification flow with token-based confirmation
- Admin approval workflow for new registrations
- `UserChecker` for pre-authentication status validation
- Registration controller with duplicate-check logic
- `ActivityLog` entity for admin audit trail
- `NotificationLog` entity for push notification history

### Changed
- `security.yaml` updated with full firewall and access control configuration
- `User` entity extended with roles, verification, and approval fields

---

## [1.1.0] – 2026-03-02

### Added
- POS (Point of Sale) system for walk-in transactions
- Search functionality across flowers, customers, and suppliers
- Reports and analytics dashboard with Chart.js integration
- Stimulus.js controllers for dynamic UI interactions
- Flower batch tracking (FlowerBatch entity and migrations)
- Advanced flower freshness logic and automatic discount service
- Admin dashboard with KPI cards and freshness distribution charts
- Report controller with date-range filtering
- `Version20260302*` migration series

---

## [1.0.0] – 2025-12-06

### Added
- Core CRUD modules: Flower, Customer, Supplier, Reservation, Payment
- Doctrine ORM entities and repositories
- Symfony Form types with server-side validation
- Twig templates with TailwindCSS styling
- Webpack Encore frontend build pipeline
- Docker Compose setup (MySQL 8.0, phpMyAdmin, Mercure)
- Initial database migrations
- Symfony Messenger transport configuration
- Base layout template and navigation

---

## [0.1.0] – 2025-10-10

### Added
- Initial Symfony 7.3 project scaffold
- Composer and npm dependency setup
- Environment variable templates (`.env`, `.env.example`)
- Basic project structure

---

[Unreleased]: https://github.com/Gye0p/Floryn_System/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/Gye0p/Floryn_System/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/Gye0p/Floryn_System/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/Gye0p/Floryn_System/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/Gye0p/Floryn_System/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/Gye0p/Floryn_System/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Gye0p/Floryn_System/compare/v0.1.0...v1.0.0
[0.1.0]: https://github.com/Gye0p/Floryn_System/releases/tag/v0.1.0
