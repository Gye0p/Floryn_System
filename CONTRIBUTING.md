# Contributing to Floryn Garden

Thank you for your interest in contributing! This project follows a structured development workflow.

## Commit Message Convention

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(optional scope): <short description>

[optional body]
[optional footer]
```

### Types

| Type | When to use |
|------|-------------|
| `feat` | A new feature |
| `fix` | A bug fix |
| `docs` | Documentation changes |
| `style` | Code formatting (no logic change) |
| `refactor` | Code restructuring (no feature/fix) |
| `perf` | Performance improvement |
| `test` | Adding or updating tests |
| `chore` | Build process, tooling, config |
| `ci` | CI/CD changes |

### Examples

```bash
feat(reservation): add status change email notification
fix(auth): resolve pending-approval loop after admin activation
docs(readme): update local setup instructions
chore(docker): add healthcheck to mysql service
refactor(flower): extract freshness calculation to service class
test(user): add unit tests for approval workflow
```

## Branch Naming

```
feat/feature-name
fix/bug-description
docs/what-is-documented
chore/task-description
```

## Pull Request Process

1. Fork the repository
2. Create your feature branch from `main`
3. Write meaningful, atomic commits
4. Ensure tests pass: `php bin/phpunit`
5. Open a Pull Request with a clear description

## Code Style

- PSR-12 PHP coding standards
- Symfony best practices
- Twig templates must extend `base.html.twig`
- All user inputs must be validated via Symfony Form types
