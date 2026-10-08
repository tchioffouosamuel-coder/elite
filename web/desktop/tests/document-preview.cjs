const { _electron: electron } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const path = require('node:path');
const os = require('node:os');

const school = { id: 1, name: 'Elites Test', code: 'ET', type: 'primaire' };
const user = { id: 1, name: 'Test Caisse', email: 'test@example.test', school_id: 1, niveau_id: null, roles: ['super_admin'],
  is_super_admin: true, est_personnel: true, permissions: [], ecoles_accessibles: [school] };
const dossier = { id: 1, eleve: { id: 1, nom_complet: 'Alice Ngono', matricule: 'ET001', classe: 'CLASS 5-B', classe_id: 1 },
  montant_scolarite: 100000, remise: 0, report_dette: 0, total_du: 100000, total_paye: 25000, reste_a_payer: 75000,
  dette_anterieure_restante: 0, reste_scolarite_a_payer: 75000, avance: 0, statut_paiement: 'partiel', taux_recouvrement: 25,
  observation: null, versements: [{ id: 1, numero_recu: 'TEST-001', date_versement: '2026-10-08', montant: 25000, mode: 'especes', annule: false }] };

(async () => {
  const pdf = execFileSync('php', [path.join(__dirname, 'receipt-fixture.php')], { maxBuffer: 10 * 1024 * 1024 });
  assert(pdf.subarray(0, 5).toString() === '%PDF-');
  const env = { ...process.env };
  delete env.ELECTRON_RUN_AS_NODE;
  const app = await electron.launch({ executablePath: require('electron'), args: [path.join(__dirname, 'preview-main.cjs')], env });
  try {
    const page = await app.firstWindow();
    await page.waitForURL(/^file:/);
    await page.waitForLoadState('domcontentloaded');
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('**/api/v1/**', async (route) => {
      const url = new URL(route.request().url());
      if (url.pathname.endsWith('/versements/1/recu')) return route.fulfill({ status: 200, contentType: 'application/pdf', body: pdf });
      let data = [];
      if (url.pathname.endsWith('/auth/me')) data = user;
      else if (url.pathname.endsWith('/desktop/statut-sync')) data = { clonage_initial_complet: true, en_attente_push: 0, ecoles: [], dernier_pull_le: null, dernier_push_le: null };
      else if (url.pathname.endsWith('/scolarite/situation')) data = { dossiers: [dossier], totaux: { effectif: 1, attendu: 100000, recouvre: 25000, reste: 75000, avances: 0, taux_recouvrement: 25, insolvables: 1 } };
      else if (url.pathname.endsWith('/schools')) data = [school];
      else if (url.pathname.endsWith('/classes')) data = [{ id: 1, nom: 'CLASS 5-B', school_id: 1 }];
      else if (url.pathname.endsWith('/notifications/non-lues')) data = { total: 0 };
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data }) });
    });
    await page.addInitScript(({ user }) => {
      localStorage.setItem('elites-school-auth', JSON.stringify({ state: { user, token: 'preview-test', activeSchoolId: null }, version: 3 }));
      localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }));
      history.replaceState(null, '', '#/caisse');
    }, { user });
    await page.reload();
    await page.locator('button[title$="TEST-001"]').click();
    await page.waitForFunction(() => document.querySelectorAll('[data-document-preview] canvas[data-rendered="true"]').length === 2);
    const ink = await page.locator('[data-document-preview] canvas').first().evaluate((canvas) => {
      const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
      let count = 0;
      for (let i = 0; i < pixels.length; i += 64) if (pixels[i + 3] && (pixels[i] < 235 || pixels[i + 1] < 235 || pixels[i + 2] < 235)) count++;
      return count;
    });
    assert(ink > 100, `PDF canvas blank: ${ink}`);
    for (const [width, height] of [[1440, 900], [1920, 1080], [375, 812], [1170, 720]]) {
      await app.evaluate(({ BrowserWindow }, { width, height }) => BrowserWindow.getAllWindows()[0].setContentSize(width, height), { width, height });
      await page.waitForFunction((width) => Math.abs(innerWidth - width) <= 2, width);
      const bounds = await page.locator('[data-document-preview] header').evaluate((header) => {
        const rect = header.getBoundingClientRect();
        const expected = parseFloat(getComputedStyle(document.documentElement).fontSize) * 2.25;
        const buttons = Array.from(header.querySelectorAll('button')).map((button) => {
          const r = button.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right };
        });
        return { top: rect.top, bottom: rect.bottom, expected, width: innerWidth, height: innerHeight, buttons };
      });
      assert(Math.abs(bounds.top - bounds.expected) < 1, JSON.stringify(bounds));
      for (const b of bounds.buttons) assert(b.top >= bounds.expected && b.bottom <= bounds.height && b.left >= 0 && b.right <= bounds.width, JSON.stringify(bounds));
      if (width === 375) await page.screenshot({ path: path.join(os.tmpdir(), 'elites-receipt-mobile.png') });
    }
    await page.getByRole('button', { name: 'Zoom avant', exact: true }).click();
    await page.waitForFunction(() => document.querySelectorAll('canvas[data-rendered="true"]').length === 2);
    await page.screenshot({ path: path.join(os.tmpdir(), 'elites-receipt-desktop.png') });
    await page.evaluate(() => {
      new MutationObserver((changes) => {
        for (const change of changes) for (const node of change.addedNodes) {
          if (node instanceof HTMLIFrameElement) node.contentWindow.print = () => {
            window.__printed = Array.from(node.contentDocument.images).map((image) => image.naturalWidth);
            node.contentWindow.dispatchEvent(new Event('afterprint'));
          };
        }
      }).observe(document.body, { childList: true });
    });
    await page.getByRole('button', { name: 'Imprimer', exact: true }).click();
    await page.waitForFunction(() => window.__printed?.length === 2);
    const printed = await page.evaluate(() => window.__printed);
    assert(printed.every((width) => width > 500));
    await page.getByRole('button', { name: "Fermer l'apercu", exact: true }).click();
    assert(await page.locator('[data-document-preview]').count() === 0);
    await page.locator('button[title$="TEST-001"]').click();
    await page.waitForFunction(() => document.querySelectorAll('[data-document-preview] canvas[data-rendered="true"]').length === 2);
    await page.keyboard.press('Escape');
    assert(await page.locator('[data-document-preview]').count() === 0);
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ result: 'passed', protocol: new URL(page.url()).protocol, pdfPages: 2, inkPixels: ink, printedPageWidths: printed, checks: ['nonblank PDF', 'toolbar bounds at desktop and mobile sizes', 'zoom', 'all print pages ready', 'close and reopen', 'Escape', 'no renderer errors'] }));
  } catch (error) {
    const page = await app.firstWindow();
    console.error(await page.locator('body').innerText());
    await page.screenshot({ path: path.join(os.tmpdir(), 'elites-receipt-failure.png') });
    throw error;
  } finally { await app.close(); }
})().catch((error) => { console.error(error); process.exit(1); });
