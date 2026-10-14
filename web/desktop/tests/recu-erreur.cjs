// Un reçu qui échoue doit le dire. Les appels à `ouvrirDocument` sont presque
// tous des `onClick={() => ouvrirDocument(...)}` sans `catch` : avant le
// correctif de `download.ts`, l'échec ne produisait qu'un rejet de promesse non
// traité — à l'écran, un bouton qui ne faisait rien.
const { _electron: electron } = require('playwright');
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
  const env = { ...process.env };
  delete env.ELECTRON_RUN_AS_NODE;
  const app = await electron.launch({ executablePath: require('electron'), args: [path.join(__dirname, 'preview-main.cjs')], env });
  try {
    const page = await app.firstWindow();
    await page.waitForURL(/^file:/);
    await page.waitForLoadState('domcontentloaded');
    let recuAppele = false;
    await page.route('**/api/v1/**', async (route) => {
      const url = new URL(route.request().url());
      // Le reçu échoue, comme le ferait un serveur PHP indisponible.
      if (url.pathname.endsWith('/versements/1/recu')) {
        recuAppele = true;
        return route.fulfill({ status: 500, contentType: 'application/json',
          body: JSON.stringify({ message: 'Generation du recu impossible.' }) });
      }
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

    const popup = page.locator('.swal2-popup');
    await popup.first().waitFor({ timeout: 10000 });
    const message = (await popup.first().innerText()).replace(/\s+/g, ' ').trim();

    assert(recuAppele, 'le reçu aurait dû être demandé');
    assert(message.length > 0, 'le message affiché ne doit pas être vide');
    // Un aperçu vide laissé ouvert serait pire que pas d'aperçu du tout :
    // l'utilisateur croirait le document en cours de chargement.
    assert(await page.locator('[data-document-preview]').count() === 0, 'aucun aperçu ne doit rester ouvert');
    console.log(JSON.stringify({ result: 'passed', message, checks: ['echec signale a l ecran', 'aucun apercu vide'] }));
  } catch (error) {
    const page = await app.firstWindow();
    console.error(await page.locator('body').innerText());
    throw error;
  } finally { await app.close(); }
})().catch((error) => { console.error(error); process.exit(1); });
