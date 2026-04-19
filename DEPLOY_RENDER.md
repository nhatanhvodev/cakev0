# Deploy Cake Bakery to Render

## 1. Prepare Environment

1. Copy `.env.example` to `.env` for local testing.
2. Fill values for DB, VNPAY, Clerk and UploadThing.
3. Keep `APP_BASE_PATH=/cakev0` for current code compatibility.
4. Run DB migration for Clerk mapping column:

```sql
SOURCE database/migrations/20260418_add_clerk_user_id.sql;
```

## 2. Local Docker Test

```bash
docker build -t cake-bakery .
docker run --rm -p 8080:80 --env-file .env cake-bakery
```

Open: `http://localhost:8080/cakev0/`

## 3. Deploy to Render

1. Push this repository.
2. In Render, create a new Web Service from this repo.
3. Render will detect `render.yaml` and deploy with Docker.
4. Set all `sync: false` variables in Render Dashboard.

## 4. Required Variables on Render

- `APP_ORIGIN` (example: `https://your-app.onrender.com`)
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, `VNPAY_RETURN_URL`
- `CLERK_PUBLISHABLE_KEY`, `CLERK_SECRET_KEY`
- `CLERK_AUTHORIZED_PARTIES` (example: `https://your-app.onrender.com`)
- `UPLOADTHING_API_KEY`
- `UPLOADTHING_TOKEN` (optional alias for backward compatibility)
- `UPLOADTHING_APP_ID` (optional)

## 5. Notes

- Current app still contains many hardcoded `/cakev0/` links. This sprint keeps compatibility by serving app under `/cakev0` path.
- Apache vhost keeps both `/cakev0` and `/Cake` aliases, but `/cakev0` is the primary path on Render.
- Clerk auth bridge is now active via `pages/clerk-session.php` and requires `users.clerk_user_id`.
- Product/avatar uploads now try UploadThing first and fallback to local storage if UploadThing is not configured or unavailable.
