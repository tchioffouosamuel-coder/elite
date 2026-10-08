const { app, BrowserWindow, ipcMain, session } = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');

app.setPath('userData', fs.mkdtempSync(path.join(os.tmpdir(), 'elites-pdf-test-')));
app.whenReady().then(() => {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => callback({ responseHeaders: {
    ...details.responseHeaders,
    'Content-Security-Policy': ["default-src 'self' 'unsafe-inline' data: blob: http: https:; frame-src 'self' data: blob: chrome-extension:"],
  } }));
  const window = new BrowserWindow({ width: 1440, height: 900, frame: false, show: false,
    webPreferences: { preload: path.resolve(__dirname, '../src/preload.cjs'), contextIsolation: true, nodeIntegration: false, plugins: true, offscreen: true, backgroundThrottling: false } });
  ipcMain.handle('desktop:get-app-version', () => app.getVersion());
  ipcMain.handle('desktop:window-minimize', () => window.minimize());
  ipcMain.handle('desktop:window-toggle-maximize', () => window.isMaximized() ? window.unmaximize() : window.maximize());
  ipcMain.handle('desktop:window-close', () => window.close());
  window.loadFile(path.resolve(process.env.ELITES_PREVIEW_DIST || path.join(__dirname, '../../dist'), 'index.html'));
});
app.on('window-all-closed', () => app.quit());
