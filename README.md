# 🌸 Floryn Garden – Flower Shop Management System

A full-stack web application built with **Symfony 7.3** for managing all operations of a flower shop — inventory, customers, reservations, point-of-sale, payments, and real-time notifications.

---

## ✨ Features

| Module | Description |
|--------|-------------|
| 🌺 **Flower Inventory** | CRUD with freshness tracking and automatic discount logic |
| 💐 **Bouquet Builder** | Compose and manage custom bouquet products |
| 👤 **Customer Management** | Full customer profiles and purchase history |
| 🏭 **Supplier Management** | Track suppliers and flower batches |
| 📅 **Reservation System** | Status-driven reservation workflow |
| 🛒 **Point of Sale (POS)** | Walk-in sales with cart and payment processing |
| 💳 **Payment Management** | Record and track payments per transaction |
| 📊 **Reports & Analytics** | Sales, inventory, and freshness analytics with charts |
| 🔐 **Authentication** | Email/password, Google OAuth, JWT API tokens |
| 👑 **Admin Dashboard** | User management, role assignment, activity logs |
| 🔔 **Real-time Notifications** | Powered by Symfony Mercure (SSE push) |
| 📱 **REST API** | JWT + refresh tokens; customer API at `/api/customer/*` |
| 📲 **Mobile docs** | [docs/API.md](docs/API.md), [MOBILE_SYNC_GUIDE.md](docs/MOBILE_SYNC_GUIDE.md) |

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.2, Symfony 7.3
- **ORM**: Doctrine ORM 3.x with Migrations
- **Frontend**: Twig, TailwindCSS, Webpack Encore, Stimulus.js
- **Database**: MySQL 8.0
- **Auth**: LexikJWT, KnpU OAuth2 (Google), Symfony Security
- **Real-time**: Symfony Mercure Bundle
- **Containerization**: Docker, Docker Compose
- **Deployment**: Railway

---

## 🚀 Getting Started (Local Development)

### Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- Docker Desktop

### 1. Clone the repository

```bash
git clone https://github.com/Gye0p/Floryn_System.git
cd Floryn_System
```

### 2. Copy environment file

```bash
cp .env.example .env.local
# Edit .env.local with your local values
```

### 3. Start Docker services

```bash
docker-compose up -d
```

This starts:
- **MySQL 8.0** on port `3309`
- **phpMyAdmin** on port `8080` → http://localhost:8080
- **Mercure Hub** on port `3000`

### 4. Install dependencies

```bash
composer install
npm install
```

### 5. Generate JWT keys

```bash
php bin/console lexik:jwt:generate-keypair
```

### 6. Run database migrations

```bash
php bin/console doctrine:migrations:migrate
```

### 7. (Optional) Load fixtures

```bash
php bin/console doctrine:fixtures:load
```

### 8. Build frontend assets

```bash
npm run dev
# or for production:
npm run build
```

### 9. Start the Symfony development server

```bash
symfony serve
```

App will be available at: **http://localhost:8000**

---

## 🌐 Environment Variables

| Variable | Description |
|----------|-------------|
| `APP_ENV` | `dev` or `prod` |
| `APP_SECRET` | Symfony secret key (must be unique & random) |
| `DATABASE_URL` | MySQL connection string |
| `JWT_PASSPHRASE` | Passphrase for RSA private key |
| `GOOGLE_CLIENT_ID` | Google OAuth App Client ID |
| `GOOGLE_CLIENT_SECRET` | Google OAuth App Client Secret |
| `MERCURE_URL` | Internal Mercure hub URL (backend publisher) |
| `MERCURE_PUBLIC_URL` | Public Mercure hub URL (clients subscribe) |
| `MERCURE_JWT_SECRET` | Shared JWT secret for Mercure |

> ⚠️ **Never commit `.env.local` to version control.** Use `.env.example` as a reference template.

---

## 🗄️ Database Schema

The schema is managed via Doctrine Migrations. Key entities:

- `User` — authentication, roles, approval status
- `Flower` — inventory with freshness tracking
- `FlowerBatch` — supplier delivery batches
- `Bouquet` / `BouquetItem` — composed products
- `Customer` — customer profiles
- `Supplier` — supplier directory
- `Reservation` / `ReservationDetail` — booking system
- `Payment` — transaction records
- `ActivityLog` — admin audit trail
- `NotificationLog` — push notification history

---

## 📁 Project Structure

```
Floryn_System/
├── src/
│   ├── Controller/     # 30 route controllers (web + API)
│   ├── Entity/         # 12 Doctrine entities
│   ├── Form/           # Symfony Form types
│   ├── Repository/     # Doctrine repositories
│   ├── Security/       # Custom authenticators & user checker
│   ├── Service/        # Business logic services
│   ├── EventSubscriber/
│   └── Twig/           # Custom Twig extensions
├── templates/          # Twig HTML templates
├── migrations/         # 17 database migrations
├── assets/             # JS/CSS frontend source
├── config/             # Symfony configuration
├── public/             # Web root
└── docker-compose.yml  # Local dev containers
```

---

## 🚢 Deployment (Railway)

This application is deployed on [Railway](https://railway.app).

**Environment variables** are configured in the Railway project dashboard. The application uses a Railway-managed MySQL database.

On deploy, the container automatically:
1. Installs Composer dependencies (`--no-dev --optimize-autoloader`)
2. Generates JWT keypair
3. Compiles frontend assets (`npm run build`)
4. Runs database migrations

---

## 📚 API & Mobile Documentation

| Document | Purpose |
|----------|---------|
| [docs/API.md](docs/API.md) | Full route list with request/response samples |
| [docs/MOBILE_SYNC_GUIDE.md](docs/MOBILE_SYNC_GUIDE.md) | Real-time sync (Mercure + FCM) setup |
| [docs/DEMO_CHECKLIST.md](docs/DEMO_CHECKLIST.md) | Presentation demo script |

---

## 🧪 Running Tests

```bash
php bin/phpunit
```

---

## 📄 License

This project is proprietary software developed as an academic project.

---

## 👤 Author

**[Your Name]** — Web Development 2 Final Project  
[Your School] | [Your Section/Course]
