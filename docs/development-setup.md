# Fresh-system development setup

Use this guide to resume work from another computer. Source code, lockfiles, setup instructions, and the Android Gradle wrapper are tracked in Git. Local credentials, MySQL data, and generated build output are deliberately excluded; configure a new local environment or restore a database backup separately.

## 1. Pull the project

For an existing checkout:

```bash
git pull origin main
```

Or clone it:

```bash
git clone https://github.com/smartmithilesh/Task-Management-System.git
cd Task-Management-System
```

## 2. Install the required tools

For the browser and backend, install PHP 8.3 or newer with the extensions listed by the installer, Composer 2, MySQL 8+, Node.js 20+, npm, and Apache with rewrite support. Flutter is optional unless continuing mobile work. Android builds also need JDK 17+ and the Android SDK; iOS builds need macOS and Xcode.

Install backend dependencies:

```bash
cd backend
composer install
cd ..
```

Build the browser client and publish it into Laravel's public directory:

```bash
cd web
npm ci
npm run build
cd ..
```

For mobile work, install Flutter stable and resolve the locked packages:

```bash
cd mobile
flutter pub get
```

## 3. Configure MySQL and Apache

Create an empty MySQL database using `utf8mb4` and a dedicated application user with access only to that database. Do not use the MySQL root account for the application. Set the local Apache document root to `backend/public`. If the host requires the repository root as its document root, allow the root `.htaccess` rules to forward requests into `backend/public`.

Add a local hosts-file entry for the chosen development hostname, for example:

```text
127.0.0.1 taskmangment.local
```

Start MySQL and Apache, then visit `http://taskmangment.local/install`. Enter the MySQL database and application administrator details in the installer. It writes the private `backend/.env`, applies migrations, seeds baseline records, and creates the first administrator. Never copy `.env` or signing credentials through Git.

## 4. Resume work

Use [the phase tracker](phase-tracker.md) to pick up the next incomplete task. The local browser app is served by Apache at the configured hostname; do not use a separate static-only web server for sign-in because the browser and API need the same Laravel session and MySQL database.

If you need the same users and task data on the new computer, make a private MySQL backup on the original system and restore it through the documented [backup and restore procedure](operations/backup-and-upgrade.md). Do not put that backup in Git.
