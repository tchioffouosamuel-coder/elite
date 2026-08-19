# Elites School Desktop

The desktop app reuses the web build and stores its local cache and pending
mutations in SQLite under the Electron user data directory.

From this directory:

```powershell
npm install
npm run dev
npm run build
```

`npm run build` creates the Windows NSIS installer in `dist/`. The API URL is
read from `VITE_API_URL` during the web build and defaults to the existing API
URL.