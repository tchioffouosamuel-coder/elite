import assert from 'node:assert/strict'
import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'
import { chromium } from 'playwright'

const base = process.env.ENCAISSEMENT_TEST_URL ?? 'http://127.0.0.1:5184'
const artifacts = join(process.env.TEMP ?? '.', 'elites-encaissement-actions')
await mkdir(artifacts, { recursive: true })
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL })
const permissions = ['finance.view', 'finance.encaisser', 'finance.annuler', 'remises.create', 'remises.update', 'remises.delete', 'dettes_anterieures.create', 'dettes_anterieures.update', 'dettes_anterieures.delete', 'dettes_anterieures.oublier']
let activePage

async function session(allowed = permissions, viewport = { width: 1280, height: 900 }) {
  const school = { id: 1, name: 'Elites Primaire', type: 'primaire', code: 'PRI' }
  const user = { id: 1, name: 'Économe', email: 'test@example.test', school_id: 1, roles: [], is_super_admin: false,
    est_enseignant: false, perimetre_borne: false, permissions: allowed, ecoles_accessibles: [school], attributions: [] }
  const context = await browser.newContext({ viewport })
  await context.addInitScript((user) => {
    localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'test-token', user, activeSchoolId: 1 }, version: 3 }))
    localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }))
  }, user)
  const page = await context.newPage()
  page.setDefaultTimeout(20000)
  activePage = page
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))
  const eleve = { id: 7, nom_complet: 'NANA Elise', matricule: 'EL007', classe: 'CM2', classe_id: 1 }
  let remises = [{ id: 21, eleve, montant: 5000, motif: 'Famille', annee_scolaire: '2026–2027' }]
  let dettes = [{ id: 31, eleve, montant: 10000, motif: '2025–2026', imputee: true }, { id: 32, eleve, montant: 2000, motif: 'Correction', imputee: false }]
  const versements = [
    { id: 41, numero_recu: 'R001', montant: 12000, annule: false },
    { id: 42, numero_recu: 'R002', montant: 8000, annule: false },
    { id: 43, numero_recu: 'R003', montant: 4000, annule: true },
  ].map((v) => ({ ...v, date_versement: '2026-10-19', heure_versement: '09:30:00', mode: 'especes', cree_par: 'Économe', created_at: '2026-10-19T09:30:00Z', annule_par: null, annule_le: null, motif_annulation: null }))
  let detteRestante = 10000
  let failRemise = false
  const requests = []
  const dossier = () => {
    const remise = remises.reduce((sum, r) => sum + r.montant, 0)
    const total_paye = versements.filter((v) => !v.annule).reduce((sum, v) => sum + v.montant, 0)
    const total_du = 100000 - remise + detteRestante
    return { id: 1, eleve, montant_scolarite: 100000, remise, report_dette: detteRestante, total_du, total_paye,
      reste_a_payer: total_du - total_paye, dette_anterieure_restante: detteRestante,
      reste_scolarite_a_payer: 100000 - remise - total_paye, avance: 0, statut_paiement: 'partiel', taux_recouvrement: 20,
      versements, observation: null, rubriques: [
        { cle: 'report_dette', libelle: 'Dette antérieure', montant_du: detteRestante, montant_paye: 0, reste: detteRestante },
        { cle: 'scolarite', libelle: 'Scolarité', montant_du: 100000 - remise, montant_paye: total_paye, reste: 100000 - remise - total_paye },
      ] }
  }
  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const path = new URL(req.url()).pathname.replace('/api/v1', '')
    const method = req.method()
    const payload = req.postDataJSON()
    requests.push({ path, method, payload })
    let data = []
    if (path === '/auth/me') data = user
    else if (path === '/schools') data = [school]
    else if (path === '/eleves/7/scolarite') data = dossier()
    else if (path === '/eleves/7/remises') data = remises
    else if (path === '/eleves/7/dettes-anterieures') data = dettes
    else if (path === '/remises/21' && method === 'PUT') {
      if (failRemise) return route.fulfill({ status: 422, json: { message: 'Modification refusée pour ce test.' } })
      Object.assign(remises[0], payload)
      data = remises[0]
    } else if (path === '/remises/21' && method === 'DELETE') remises = []
    else if (path === '/dettes-anterieures/31' && method === 'PUT') {
      detteRestante = payload.montant
      Object.assign(dettes[0], payload)
      data = dettes[0]
    } else if (path === '/dettes-anterieures/32' && method === 'DELETE') dettes = dettes.filter((d) => d.id !== 32)
    else if (path === '/eleves/7/dettes-anterieures/oublier' && method === 'POST') detteRestante = 0
    else if (path === '/versements/42/corriger' && method === 'POST') {
      versements[1].annule = true
      versements.push({ ...versements[1], ...payload, id: 44, numero_recu: 'R004', annule: false })
      data = { versement_id: 44, numero_recu: 'R004' }
    } else if (path === '/versements/41/annuler' && method === 'POST') versements[0].annule = true
    else if (method !== 'GET') throw new Error(`Unexpected mutation ${method} ${path}`)
    await route.fulfill({ json: { success: true, data } })
  })
  await page.goto(`${base}/#/caisse/encaisser/7`)
  await page.getByRole('heading', { name: 'Encaisser — NANA Elise' }).waitFor()
  return { page, context, requests, errors, dossier, failRemise: (value) => { failRemise = value } }
}

async function checkBalance(s) {
  const expected = String(s.dossier().reste_a_payer)
  await s.page.waitForFunction((expected) => {
    const field = [...document.querySelectorAll('label')].find((el) => el.textContent.includes('Montant reçu (F CFA)'))?.querySelector('input')
    return field?.value.replace(/\D/g, '') === expected
  }, expected)
  assert.equal(new URL(s.page.url()).hash, '#/caisse/encaisser/7')
}
const modal = (page) => page.locator('[role="presentation"]').filter({ has: page.getByRole('button', { name: 'Fermer', exact: true }) })
const confirm = (page) => page.locator('.swal2-confirm').click()
const cancel = (page) => page.locator('.swal2-cancel').click()

try {
  const s = await session()
  const { page } = s
  await checkBalance(s)
  await page.screenshot({ path: join(artifacts, 'desktop.png'), fullPage: true })
  assert.equal(await page.getByTitle('Modifier le paiement R003', { exact: true }).count(), 0)
  assert.equal(await page.getByTitle('Annuler le paiement R003', { exact: true }).count(), 0)

  await page.getByRole('button', { name: 'Gérer les remises', exact: true }).click()
  await page.getByTitle('Modifier la remise', { exact: true }).click()
  await page.getByLabel('Montant de la remise (F CFA)', { exact: true }).fill('7000')
  s.failRemise(true)
  await modal(page).getByRole('button', { name: 'Enregistrer', exact: true }).click()
  await page.getByText('Modification refusée pour ce test.', { exact: true }).waitFor()
  assert.equal(s.dossier().remise, 5000)
  s.failRemise(false)
  await modal(page).getByRole('button', { name: 'Enregistrer', exact: true }).click()
  await page.getByLabel('Montant de la remise (F CFA)', { exact: true }).waitFor({ state: 'hidden' })
  await checkBalance(s)
  await page.getByTitle('Supprimer la remise', { exact: true }).click()
  await cancel(page)
  assert.equal(s.requests.filter((r) => r.method === 'DELETE').length, 0)
  await page.getByTitle('Supprimer la remise', { exact: true }).click()
  await confirm(page)
  await page.getByText('Aucune remise.', { exact: true }).waitFor()
  await checkBalance(s)
  await page.getByRole('button', { name: 'Fermer', exact: true }).click()

  await page.getByTitle('Modifier le paiement R002', { exact: true }).click()
  await page.getByLabel('Montant corrigé (F CFA)', { exact: true }).fill('6000')
  await modal(page).getByRole('button', { name: 'Corriger le reçu', exact: true }).click()
  await page.getByText('R004', { exact: true }).waitFor()
  await checkBalance(s)
  await page.getByTitle('Annuler le paiement R001', { exact: true }).click()
  await cancel(page)
  assert.equal(s.requests.filter((r) => r.path.endsWith('/annuler')).length, 0)
  await page.getByTitle('Annuler le paiement R001', { exact: true }).click()
  await confirm(page)
  await page.getByTitle('Annuler le paiement R001', { exact: true }).waitFor({ state: 'hidden' })
  await checkBalance(s)

  await page.getByRole('button', { name: 'Gérer la dette antérieure', exact: true }).click()
  await page.getByTitle('Modifier la dette', { exact: true }).first().click()
  await page.getByLabel('Montant de la dette (F CFA)', { exact: true }).fill('8000')
  await modal(page).getByRole('button', { name: 'Enregistrer', exact: true }).click()
  await page.getByLabel('Montant de la dette (F CFA)', { exact: true }).waitFor({ state: 'hidden' })
  await checkBalance(s)
  assert.equal(await page.getByTitle('Supprimer la dette', { exact: true }).count(), 1, 'Only unallocated debt can be removed individually')
  await page.getByTitle('Supprimer la dette', { exact: true }).click()
  await confirm(page)
  await page.getByTitle('Supprimer la dette', { exact: true }).waitFor({ state: 'hidden' })
  await modal(page).getByRole('button', { name: /Effacer la dette restante/ }).click()
  await confirm(page)
  await modal(page).getByRole('button', { name: /Effacer la dette restante/ }).waitFor({ state: 'hidden' })
  await checkBalance(s)
  await page.getByRole('button', { name: 'Fermer', exact: true }).click()
  assert.equal(s.requests.some((r) => r.path.includes('/encaisser')), false, 'Management must not submit a payment')
  assert.deepEqual(s.errors, [])
  await s.context.close()

  const limited = await session(['finance.view', 'finance.encaisser', 'remises.update', 'dettes_anterieures.update'])
  assert.equal(await limited.page.getByTitle(/Annuler le paiement|Modifier le paiement/).count(), 0)
  await limited.page.getByRole('button', { name: 'Gérer les remises', exact: true }).click()
  await limited.page.getByTitle('Modifier la remise', { exact: true }).waitFor()
  assert.equal(await limited.page.getByTitle('Supprimer la remise', { exact: true }).count(), 0)
  assert.equal(await modal(limited.page).getByRole('button', { name: 'Accorder une remise', exact: true }).count(), 0)
  await limited.page.getByRole('button', { name: 'Fermer', exact: true }).click()
  await limited.page.getByRole('button', { name: 'Gérer la dette antérieure', exact: true }).click()
  await limited.page.getByTitle('Modifier la dette', { exact: true }).first().waitFor()
  assert.equal(await limited.page.getByTitle('Supprimer la dette', { exact: true }).count(), 0)
  assert.equal(await modal(limited.page).getByRole('button', { name: /Effacer la dette restante|Enregistrer une dette/ }).count(), 0)
  assert.deepEqual(limited.errors, [])
  await limited.context.close()

  const mobile = await session(permissions, { width: 360, height: 800 })
  await mobile.page.getByRole('button', { name: 'Gérer la dette antérieure', exact: true }).scrollIntoViewIfNeeded()
  assert.equal(await mobile.page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true)
  await mobile.page.screenshot({ path: join(artifacts, 'mobile.png'), fullPage: true })
  assert.deepEqual(mobile.errors, [])
  await mobile.context.close()

  const cashier = await session(['finance.view', 'finance.encaisser'])
  assert.equal(await cashier.page.getByRole('button', { name: /Gérer les remises|Gérer la dette antérieure/ }).count(), 0)
  assert.deepEqual(cashier.errors, [])
  await cashier.context.close()
  console.log(`Encaissement actions passed. Screenshots: ${artifacts}`)
} catch (error) {
  if (activePage && !activePage.isClosed()) await activePage.screenshot({ path: join(artifacts, 'failure.png'), fullPage: true })
  throw error
} finally {
  await browser.close()
}
