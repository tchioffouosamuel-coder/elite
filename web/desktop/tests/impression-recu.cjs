// L'image envoyée à l'imprimante doit être celle que pdf.js sait rendre.
//
// pdf.js déclare les polices embarquées d'un PDF dans le document où il a été
// chargé, et un contexte 2D ne résout ses polices que dans le document de son
// propre canvas : rasteriser les pages dans un canvas de l'iframe
// d'impression composait tout le texte avec une police de repli, sans aucun
// glyphe aux codes privés qu'utilise pdf.js. Le ticket sortait entièrement en
// carrés vides sur le papier, alors que l'aperçu restait correct — un défaut
// qu'un test ne regardant que les dimensions des images ne peut pas voir.
//
// On compare donc, pixel à pixel, la page imprimée au rendu de référence de
// la même page au même facteur d'échelle.
const { _electron: electron } = require('playwright');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const path = require('node:path');

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

    // `print()` neutralisé, et pas d'`afterprint` : le nettoyage révoquerait
    // les URL des images avant qu'on ait pu les relire.
    await page.evaluate(() => {
      new MutationObserver((changes) => {
        for (const change of changes) for (const node of change.addedNodes) {
          if (node instanceof HTMLIFrameElement) node.contentWindow.print = () => {
            window.__imprime = Array.from(node.contentDocument.images).map((image) => image.src);
          };
        }
      }).observe(document.body, { childList: true });
    });
    await page.getByRole('button', { name: 'Imprimer', exact: true }).click();
    await page.waitForFunction(() => window.__imprime?.length === 2, null, { timeout: 30000 });

    const mesures = await page.evaluate(async () => {
      const lire = async (source, largeur, hauteur) => {
        const image = new Image();
        image.src = source;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = largeur ?? image.naturalWidth;
        canvas.height = hauteur ?? image.naturalHeight;
        canvas.getContext('2d').drawImage(image, 0, 0);
        return canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
      };

      const resultats = [];
      for (const [index, source] of window.__imprime.entries()) {
        const imprime = await lire(source);
        // Référence : la même page, rendue par l'aperçu dans le document
        // principal, redimensionnée à la taille de la page imprimée.
        const apercu = document.querySelectorAll('[data-document-preview] canvas')[index];
        const reference = await lire(apercu.toDataURL('image/png'), apercu.width, apercu.height);

        const encre = (pixels) => {
          let n = 0;
          for (let i = 0; i < pixels.length; i += 4) {
            if (pixels[i + 3] && (pixels[i] < 200 || pixels[i + 1] < 200 || pixels[i + 2] < 200)) n++;
          }
          return n / (pixels.length / 4);
        };
        resultats.push({ page: index + 1, encreImprimee: encre(imprime), encreReference: encre(reference) });
      }
      return resultats;
    });

    // Le texte composé avec une police de repli ne dessine que le contour de
    // carrés vides : la surface encrée s'effondre par rapport au rendu que
    // pdf.js produit avec les polices du document. Correctement imprimée, une
    // page reste à 2 % près de sa référence — le seuil laisse donc une marge
    // confortable à l'anticrénelage tout en voyant passer le moindre repli.
    for (const m of mesures) {
      assert(
        m.encreImprimee > m.encreReference * 0.8,
        `Page ${m.page} imprimée trop pâle (${(m.encreImprimee * 100).toFixed(2)} % contre ${(m.encreReference * 100).toFixed(2)} % en référence) `
        + '— signe que le texte est rendu avec une police de repli, en carrés vides.',
      );
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ result: 'passed', mesures, checks: ['page imprimee aussi encree que le rendu de reference', 'no renderer errors'] }));
  } catch (error) {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
  } finally {
    await app.close();
  }
})().catch((e) => { console.error(e); process.exit(1); });
