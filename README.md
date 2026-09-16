# Store App API Backend

Plain PHP/MySQL backend for the Flutter app API calls.

## Upload Layout

Upload the **contents** of this `api_backend` folder to the server folder used by the Flutter API base URL.

Current Flutter base URL:

```text
http://f30-preview.awardspace.net/institueproject.com/medical.com/api
```

That means these files should be uploaded inside the server `api` folder so routes resolve like:

```text
/api/login
/api/product/show/all
/api/centers/products
```

## Setup

1. Create a MySQL database.
2. Import `schema.sql`.
3. Copy `config.example.php` to `config.php`.
4. Edit `config.php`:

```php
return [
    'db_host' => 'localhost',
    'db_name' => 'YOUR_DATABASE',
    'db_user' => 'YOUR_DATABASE_USER',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'base_url' => 'https://your-domain.com/path/to/api',
    'upload_dir' => __DIR__ . '/uploads',
];
```

5. Make sure `uploads/` is writable by PHP. If it does not exist, the backend tries to create it.

## Railway Deployment

Yes, this backend can be deployed through GitHub + Railway.

Recommended setup:

1. Push this repository to GitHub.
2. In Railway, create a new project from the GitHub repository.
3. Set the service root directory to:

```text
api_backend
```

4. Add a Railway MySQL service to the same project.
5. In the PHP service variables, reference the MySQL variables:

```text
MYSQLHOST=${{MySQL.MYSQLHOST}}
MYSQLPORT=${{MySQL.MYSQLPORT}}
MYSQLDATABASE=${{MySQL.MYSQLDATABASE}}
MYSQLUSER=${{MySQL.MYSQLUSER}}
MYSQLPASSWORD=${{MySQL.MYSQLPASSWORD}}
APP_URL=https://your-php-service-domain.up.railway.app
UPLOAD_DIR=/app/uploads
```

6. Import `schema.sql` into the Railway MySQL database.
7. Deploy the PHP service.
8. Update Flutter `AppStrings.API` to the Railway PHP service URL.

Railway/Nixpacks detects PHP from `composer.json` and `index.php` in `api_backend`.

## Response Format

Every successful response includes:

```json
{
  "error": 0,
  "message": "success"
}
```

Every failed response includes:

```json
{
  "error": 1,
  "message": "..."
}
```

This matches `BaseResModel` in the Flutter app.

## Authentication

Register and login return:

```json
{
  "user": [{ "...": "..." }],
  "roles": [],
  "access_token": "..."
}
```

All protected routes require:

```text
Authorization: Bearer YOUR_TOKEN
```

## Important Notes

- This backend is intentionally framework-free for shared hosting.
- It expects Apache `mod_rewrite` for `.htaccess`.
- `/products/multistore` supports both Excel/imported products and the bulk category update flow currently used in the app.
- Uploaded file URLs are built from `base_url`, so set it to the public URL of this API folder.
