# Elites School Desktop

The desktop app reuses the web build and stores its local cache and pending
mutations in SQLite under the Electron user data directory.

Installing a desktop update replaces the application files, not this local
database. The initial full download is only required when a desktop account
has not completed its first synchronization; later launches continue with the
saved local data and apply background synchronization incrementally. Database
migrations run only when the packaged migration list changes.

From this directory:

```powershell
npm install
npm run dev
npm run build
```

`npm run build` creates the Windows NSIS installer in `dist/`. The API URL is
read from `VITE_API_URL` during the web build and defaults to the existing API
URL.