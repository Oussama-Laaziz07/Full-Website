# Database setup

The site already uses PDO with the Aiven MySQL CA certificate in `certs/ca.pem`. The host, port, database, and username shown in the supplied screenshot are the defaults in `config/db.php`. Set these secrets in the hosting environment (for example, Vercel Project Settings → Environment Variables):

- `DB_PASSWORD`: the Aiven service password (not present in the screenshot)
- `AUTH_SECRET`: a private random string of at least 32 characters
- `CLOUDINARY_CLOUD_NAME`: the Cloudinary product environment name
- `CLOUDINARY_API_KEY`: the Cloudinary API key
- `CLOUDINARY_API_SECRET`: the Cloudinary API secret (keep this server-side; never place it in browser code)

New ad photos are uploaded from the PHP API to Cloudinary over HTTPS. Set all three Cloudinary variables in the hosting environment before enabling photo uploads. Ad submissions without a photo do not require Cloudinary configuration.

Run `moderation_migration.sql` once against `defaultdb` before deploying. Replace the example email at the bottom with the email address of the account that should administer the site. That account must register first. The SQL promotes it to `admin`; public registration always creates ordinary user accounts.

After deployment, sign in with that account and open **Administration**. New ads start in `pending_review`, public visitors only receive `active` ads, and removed ads plus appeals are managed in the admin area. Users can submit an appeal for their own removed ads from **Mes annonces**.
