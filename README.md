# Wheelsturn Automotive: Garage Management System

Workshop management, SARS-compliant invoicing, double-entry accounting and analytics for Wheelsturn Automotive.

- `backend/`: CodeIgniter 4 web app and API (PHP 8.2, MySQL)
- `mobile/`: Expo app for managers, technicians and customers (Phase 3)

## Local development
```bash
cd backend
composer install
npm install && npm run build        # Tailwind CSS + vendor JS → public/assets
php spark migrate
php spark db:seed InitialSeeder
php spark db:seed DemoSeeder        # optional demo data
php spark serve                     # http://localhost:8080
```
Login: `admin@wheelsturn.co.za` / `Admin@12345` (demo staff: `thabo@`, `sipho@`, `priya@`…`@wheelsturn.co.za` / `Demo@12345`).

Local dev uses SQLite (`writable/wheelsturn.db`); production uses MySQL. See **[DEPLOYMENT.md](DEPLOYMENT.md)**.

## Useful commands
| Command | Purpose |
|---|---|
| `php spark gms:cron` | Scheduler (run every 15 min via cron) |
| `php spark reports:weekly --to you@x.co.za` | Send the Monday performance report now |
| `npm run watch:css` | Rebuild CSS while editing views |
