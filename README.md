# wppluginspro

WordPress + WooCommerce shop for WSH plugins. Custom theme: `cubestheme`.

## Local

```bash
docker compose up -d
```

- Site: http://localhost:8097
- phpMyAdmin: http://localhost:8096 (`root` / `root`)
- WordPress is in `src/`. Database dumps in `sql/` are gitignored.

## FTP deploy

```bash
cp .deploy-ftp.env.example .deploy-ftp.env
# fill FTP_USER / FTP_PASS
./deploy-ftp.sh --list-remote
./deploy-ftp.sh --mark-synced   # once, when the server already matches this commit
./deploy-ftp.sh --dry-run
./deploy-ftp.sh
```

`wp-config.php` and `uploads` are not uploaded.
