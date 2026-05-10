# Finovate (Symfony)

Plateforme **FinTech** développée avec **Symfony 6.4**. Le projet combine des fonctionnalités de gestion financière (transactions, objectifs d’épargne, factures), un espace marketplace (produits / annonces), et des fonctionnalités communautaires.

## Fonctionnalités

- **Authentification**
  - Login/Register
  - JWT (LexikJWTAuthenticationBundle)
  - OAuth2 Google et GitHub (KnpU OAuth2 Client)
- **Espace utilisateur**
  - Dashboard
  - Transactions (transfert, reçu PDF, notifications)
  - Goals (objectifs d’épargne)
  - Bills (factures)
- **Marketplace**
  - Produits (points, stock)
  - Annonces
- **Intégrations / services**
  - Stripe (paiement)
  - Twilio (SMS)
  - reCAPTCHA
  - QR code
  - Upload d’images (VichUploader)
  - Pagination (KnpPaginator)
  - Mercure (notifications temps réel)

## Stack technique

- **Backend**: PHP 8.2+, Symfony 6.4
- **Base de données**: Doctrine ORM / Doctrine Migrations
- **Templates**: Twig
- **Sécurité**: Symfony Security, JWT (Lexik)
- **Tests**: PHPUnit

Principales dépendances (voir `composer.json`) :

- `symfony/framework-bundle` (6.4)
- `doctrine/orm` (3.x)
- `lexik/jwt-authentication-bundle`
- `knpuniversity/oauth2-client-bundle` + `league/oauth2-google` + `league/oauth2-github`
- `stripe/stripe-php`
- `twilio/sdk`
- `dompdf/dompdf`

## Prérequis

- PHP **8.2** (ou >= 8.1)
- Composer
- MySQL/MariaDB (ou autre SGBD compatible Doctrine)
- Symfony CLI (optionnel mais recommandé)

## Installation

1) Installer les dépendances

```bash
composer install
```

2) Configurer les variables d’environnement

- Dupliquer `.env` vers `.env.local` et adapter.
- Exemple (à adapter à ton environnement) :

```dotenv
APP_ENV=dev
APP_SECRET=change_me

DATABASE_URL="mysql://user:password@127.0.0.1:3306/finovate?serverVersion=8.0"

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change_me

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...

GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...

STRIPE_SECRET_KEY=...

TWILIO_ACCOUNT_SID=...
TWILIO_AUTH_TOKEN=...
TWILIO_FROM=...
```

3) Base de données & migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

4) Générer les clés JWT (si pas déjà présentes)

```bash
php bin/console lexik:jwt:generate-keypair
```

## Lancer l’application

Avec Symfony CLI :

```bash
symfony server:start
```

Ou avec le serveur PHP :

```bash
php -S 127.0.0.1:8000 -t public
```

## Tests

Lancer tous les tests :

```bash
php bin/phpunit
```

Lancer uniquement les tests de services :

```bash
php bin/phpunit tests/Service/
```

## Structure (simplifiée)

- `src/Controller` : controllers web / API
- `src/Entity` : entités Doctrine
- `src/Service` : logique métier (services)
- `tests/` : tests PHPUnit
- `config/` : configuration Symfony

## Topics / mots-clés

- symfony
- php
- fintech
- jwt
- oauth2
- doctrine
- twig
- phpunit
- stripe
- twilio
- recaptcha
- mercure

