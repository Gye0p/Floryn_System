# Floryn Garden – Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.5.x   | ✅ Yes     |
| < 1.5   | ❌ No      |

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please do **not** open a public GitHub issue.

Instead, report it privately by emailing the project maintainer. You can expect an acknowledgement within 48 hours and a resolution plan within 7 days.

## Security Best Practices Used

- **JWT Authentication** — stateless, short-lived tokens for API access
- **Google OAuth** — delegated authentication via trusted provider
- **Environment Variables** — secrets stored outside source code
- **Password Hashing** — Symfony's bcrypt/argon2 password hasher
- **CSRF Protection** — enabled on all state-changing web forms
- **CORS** — origin whitelist via NelmioCorsBundle
- **Admin Approval** — new accounts require admin activation before login
- **Role-Based Access Control** — `ROLE_ADMIN`, `ROLE_USER`, `ROLE_CUSTOMER`
