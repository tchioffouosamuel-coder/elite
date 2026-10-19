import assert from 'node:assert/strict'
import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'
import { chromium } from 'playwright'

const base = process.env.PARENT_TEST_URL ?? 'http://127.0.0.1:5184'
const artifacts = join(process.env.TEMP ?? '.', 'elites-parent-payments')
await mkdir(artifacts, { recursive: true })
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL })
try {
  for (const width of [360, 1280]) {
    // Un parent à l'étranger doit voir les dates civiles de son école.
    const context = await browser.newContext({ viewport: { width, height: 900 }, timezoneId: 'America/Los_Angeles' })
    const page = await context.newPage()
    page.setDefaultTimeout(20000)
    await page.clock.install({ time: new Date('2026-10-19T22:59:30Z') })
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    const user = { id: 1, name: 'Parent', roles: ['parent'], permissions: [], est_personnel: false,
      is_super_admin: false, ecoles_accessibles: [], school_id: null }
    await context.addInitScript((user) => {
      localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'test-parent', user, activeSchoolId: null }, version: 3 }))
    }, user)
    const tranches = [
      { id: 1, libelle: 'Tranche réglée', date_echeance: '2026-10-18', montant_paye: 100000, reste: 0, statut: 'soldee' },
      { id: 2, libelle: 'Tranche partielle', date_echeance: '2026-10-19', montant_paye: 50000, reste: 50000, statut: 'partielle' },
      { id: 3, libelle: 'Tranche future', date_echeance: '2026-10-21', montant_paye: 0, reste: 100000, statut: 'a_venir' },
      { id: 4, libelle: 'Tranche sans date', date_echeance: null, montant_paye: 0, reste: 100000, statut: 'a_venir' },
    ].map((tranche) => ({ montant: 100000, jours_restants: 999, ...tranche }))
    let finance = { total_du: 400000, total_paye: 150000, reste_a_payer: 250000,
      dette_anterieure_restante: 0, reste_scolarite_a_payer: 250000, statut_paiement: 'partiel',
      rubriques: [], versements: [], echeancier: { actif: true, retard: 0, delai_grace: 0,
        prochaine_echeance: tranches[1], tranches } }
    await page.route('**/api/v1/**', async (route) => {
      const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
      let data = []
      if (path === '/auth/me') data = user
      else if (path === '/parent/champs-manquants') data = { tuteur: null, enfants: [], total: 0 }
      else if (path === '/parent/enfants/5') data = { id: 5, nom_complet: 'Awa Ngono', sexe: 'F', tuteurs: [], sante: {}, acte_naissance: {} }
      else if (path === '/parent/enfants/5/finance') data = finance
      else if (path.endsWith('/sanctions') || path.endsWith('/notes-sequence')) data = null
      await route.fulfill({ json: { success: true, data } })
    })
    await page.goto(`${base}/#/parent/enfants/5`)
    const global = page.getByRole('progressbar', { name: 'Progression des paiements / Payment progress', exact: true })
    await global.waitFor()
    assert.equal(await global.getAttribute('aria-valuenow'), '37.5')
    for (const [name, value] of [['Tranche réglée', '100'], ['Tranche partielle', '50'], ['Tranche future', '0']]) {
      assert.equal(await page.getByRole('progressbar', { name, exact: true }).getAttribute('aria-valuenow'), value)
    }
    await page.getByText('À régler aujourd’hui / Due today', { exact: true }).waitFor()
    await page.getByText('2 jours restants / 2 days remaining', { exact: true }).waitFor()
    assert.equal(await page.getByText(/999 jours/).count(), 0)
    assert.equal(await page.getByText(/En retard de 1 jour/).count(), 0, 'Paid installments have no countdown')
    await page.getByText('Sans date limite / No due date', { exact: true }).waitFor()
    await global.scrollIntoViewIfNeeded()
    const financeCard = global.locator('xpath=../../..')
    await financeCard.screenshot({ path: join(artifacts, `paiements-${width}.png`) })
    assert.equal(await financeCard.evaluate((el) => el.scrollWidth > el.clientWidth), false, 'Financial card fits the viewport')
    // Le compteur passe au jour suivant sans nouvelle requête API.
    await page.clock.fastForward(60000)
    await page.getByText('En retard de 1 jour / 1 day overdue', { exact: true }).waitFor()
    await page.getByText('1 jour restant / 1 day remaining', { exact: true }).waitFor()
    assert.deepEqual(errors, [])
    // Progression disponible même sans découpage, sans frais ou en cas d'avance.
    for (const [total, paid, expected] of [[100000, 50000, '50'], [100000, 120000, '100'], [0, 0, '100']]) {
      finance = { ...finance, total_du: total, total_paye: paid, echeancier: { actif: false } }
      await page.reload()
      await global.waitFor()
      assert.equal(await global.getAttribute('aria-valuenow'), expected)
      assert.equal(await page.getByRole('progressbar').count(), 1)
    }
    assert.deepEqual(errors, [])
    await context.close()
  }
  console.log('Parent payments: progress, countdown at midnight, paid/no-date installments, and mobile/desktop layouts passed.')
} finally {
  await browser.close()
}
