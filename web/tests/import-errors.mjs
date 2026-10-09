import assert from 'node:assert/strict'
import { mkdir, readFile } from 'node:fs/promises'
import { join } from 'node:path'

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright')
const base = process.env.IMPORT_TEST_URL ?? 'http://127.0.0.1:5173'
const artifacts = join(process.env.TEMP ?? '.', 'elites-import-errors')
await mkdir(artifacts, { recursive: true })
const browser = await chromium.launch()
const user = {
  id: 1, name: 'Direction', email: 'test@example.test', school_id: 1, is_super_admin: true,
  roles: [], permissions: [], ecoles_accessibles: [], attributions: [],
}
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
await context.addInitScript((profile) => {
  localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'test-token', user: profile, activeSchoolId: 1 }, version: 3 }))
  localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }))
}, user)
const page = await context.newPage()
page.setDefaultTimeout(30000)
const errors = []
page.on('pageerror', (error) => errors.push(error.message))
let importResponse = { imported: 0, failed: 0 }
let importStatus = 200
let importCase = 0

await page.route('**/api/v1/**', async (route) => {
  const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
  if (path === '/depenses/import') {
    return route.fulfill({ status: importStatus, json: importStatus === 200 ? { success: true, data: importResponse } : importResponse })
  }
  let data = []
  if (path === '/auth/me') data = user
  if (path === '/depenses') data = { depenses: [], par_compte: [], totaux: { nombre: 0, engage: 0, paye: 0, total: 0, annule: 0 } }
  await route.fulfill({ json: { success: true, data, meta: { pagination: { total: 0, current_page: 1, per_page: 30, last_page: 1 } } } })
})

async function importer(result, status = 200) {
  importResponse = result
  importStatus = status
  await page.goto(`${base}/?importCase=${++importCase}#/depenses`)
  await page.getByRole('button', { name: 'Importer', exact: true }).click()
  await page.locator('input[type=file]').setInputFiles({ name: 'depenses.csv', mimeType: 'text/csv', buffer: Buffer.from('Date;Libelle;Montant\n2026-10-09;Fournitures;1000') })
  await page.getByRole('button', { name: 'Importer', exact: true }).last().click()
}

try {
  await importer({ imported: 0, failed: 2, errors: [
    { row: 4, attribute: 'libelle', errors: ['Libellé manquant.'], values: { _donnees: { date: '2026-10-09', montant: 1000 } } },
    { row: 8, attribute: 'libelle', errors: ['Libellé manquant.'], values: { _donnees: { date: '2026-10-09', montant: 2000 } } },
  ] })
  await page.getByText('libelle : Libellé manquant.', { exact: false }).waitFor()
  await page.getByRole('button', { name: 'Lignes', exact: true }).click()
  await page.getByText('Ligne 4', { exact: true }).waitFor()
  await page.getByText('Ligne 8', { exact: true }).waitFor()
  await page.getByRole('button', { name: 'Fermer', exact: true }).last().click()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exporter', exact: true }).click()
  const csv = await readFile(await (await download).path(), 'utf8')
  assert.match(csv, /"4"/)
  assert.match(csv, /"8"/)
  assert.match(csv, /montant/)
  assert.doesNotMatch(csv, /\[object Object\]/)
  await page.screenshot({ path: join(artifacts, 'depenses-erreurs-desktop.png'), fullPage: true })

  await importer({ imported: 0, failed: 1, erreurs_metier: [{ ligne: 7, nom: 'Fournitures', message: 'Dépense refusée.', donnees: { montant: 1000 } }] })
  await page.getByText('Dépense refusée.', { exact: false }).waitFor()
  await page.getByRole('button', { name: 'Lignes', exact: true }).click()
  await page.getByText('Ligne 7 · Fournitures', { exact: true }).waitFor()

  await importer({ imported: 0, failed: 1, erreurs: [{ ligne: 3, nom: 'Alice', message: 'Classe introuvable.' }] })
  await page.getByText('Classe introuvable.', { exact: false }).waitFor()

  await importer({ imported: 0, failed: 0 })
  await page.getByText("Aucune ligne n'a été importée.", { exact: false }).waitFor()

  await importer({ message: 'Le fichier est invalide.', errors: { file: ['Le fichier doit être au format XLSX, XLS ou CSV.'] } }, 422)
  await page.getByText('file : Le fichier doit être au format XLSX, XLS ou CSV.', { exact: true }).waitFor()

  await page.setViewportSize({ width: 390, height: 844 })
  await importer({ imported: 0, failed: 1, erreurs_metier: ['Une erreur métier sans numéro de ligne et avec un message suffisamment long pour vérifier que le texte reste lisible sur mobile.'] })
  await page.getByRole('button', { name: 'Lignes', exact: true }).waitFor()
  assert.equal(await page.locator('div[role=presentation]').evaluate((el) => el.scrollWidth <= el.clientWidth), true)
  await page.screenshot({ path: join(artifacts, 'depenses-erreurs-mobile.png'), fullPage: true })
  await page.getByRole('button', { name: 'Lignes', exact: true }).click()
  await page.getByText('Ligne non précisée', { exact: true }).waitFor()
  assert.deepEqual(errors, [])
  console.log(`Import details verified: validation, business errors, preinscriptions, CSV, empty result, HTTP 422, mobile. Screenshots: ${artifacts}`)
} finally {
  await browser.close()
}
