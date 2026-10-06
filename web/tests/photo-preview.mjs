import assert from 'node:assert/strict'
import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'
import { chromium } from 'playwright'

const base = process.env.PHOTO_TEST_URL ?? 'http://127.0.0.1:5174'
const artifacts = join(process.env.TEMP ?? '.', 'elites-photo-preview')
await mkdir(artifacts, { recursive: true })
const browser = await chromium.launch({ args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] })
const school = { id: 1, name: 'Elites', type: 'secondaire', code: 'ELITES' }
const classe = { id: 7, nom: '6A', school_id: 1, school, niveau: { id: 1, nom: '6e' }, sous_systeme: { id: 1, nom: 'Francophone' } }
const user = { id: 1, name: 'Agent', email: 'test@example.test', school_id: 1, roles: [], is_super_admin: false, permissions: ['eleves.view', 'eleves.update'], ecoles_accessibles: [school], attributions: [] }
const eleve = { id: 1, nom_complet: 'Alice Nga', nom: 'Nga', prenom: 'Alice', sexe: 'F', matricule: 'EL001', statut: 'actif', inscrit_annee_active: true, classe, school, school_id: 1, classe_id: 7, photo_url: `${base}/photo-fixture.png`, tuteurs: [], acte_naissance: {}, sante: { aptitude: 'apte' }, redoublant: false }
let activePage

async function session(profile, viewport = { width: 1280, height: 900 }) {
  const context = await browser.newContext({ viewport, permissions: ['camera'] })
  await context.addInitScript(({ profile }) => {
    localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'test-token', user: profile, activeSchoolId: 1 }, version: 3 }))
    localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }))
  }, { profile })
  const page = await context.newPage()
  activePage = page
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))
  const png = await page.evaluate(() => {
    const canvas = document.createElement('canvas')
    canvas.width = 400; canvas.height = 500
    const ctx = canvas.getContext('2d')
    ctx.fillStyle = '#b0dfcc'; ctx.fillRect(0, 0, 400, 500)
    ctx.fillStyle = '#335755'; ctx.fillRect(100, 100, 200, 300)
    return canvas.toDataURL('image/png').split(',')[1]
  })
  const image = Buffer.from(png, 'base64')
  let photo = eleve.photo_url
  let uploadFails = false
  const requests = []
  await page.route('**/photo-fixture.png*', async (route) => {
    assert.equal(route.request().headers().authorization, undefined, 'public photo must not receive API token')
    await route.fulfill({ contentType: 'image/png', body: image })
  })
  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const path = new URL(req.url()).pathname.replace('/api/v1', '')
    requests.push({ method: req.method(), path, body: req.postData() })
    let data = []
    if (path === '/auth/me') data = profile
    else if (path === '/parent/champs-manquants') data = { total: 0, enfants: [], tuteur: null }
    else if (path === '/schools') data = [school]
    else if (path === '/classes') data = [classe]
    else if (path === '/eleves' || path === '/parent/enfants') data = [{ ...eleve, photo_url: photo }]
    else if (path === '/parent/enfants/1') data = { ...eleve, photo_url: photo }
    else if (path === '/parent/enfants/1/sanctions') data = { sanctions: [], est_exclu: false, motif_exclusion: null }
    else if (path === '/parent/enfants/1/finance' || path === '/parent/enfants/1/lecons-semaine' || path === '/parent/enfants/1/assiduite') data = null
    else if (path === '/eleves/1/photo') {
      if (req.method() === 'POST') {
        if (uploadFails) return route.fulfill({ status: 422, json: { message: 'Photo refusée' } })
        assert.match(req.headers()['content-type'], /multipart\/form-data/)
        assert.match(req.postData(), /name="photo"/)
        photo = `${base}/photo-fixture.png?updated=${requests.length}`
      } else if (req.method() === 'DELETE') photo = null
      data = { ...eleve, photo_url: photo }
    } else if (path === '/parent/enfants/1/modification') data = { id: 1, statut: 'en_attente', donnees: {}, created_at: '2026-10-06T10:00:00Z' }
    await route.fulfill({ json: { success: true, data, meta: { pagination: { total: 1, current_page: 1, per_page: 1000, last_page: 1 } } } })
  })
  return { context, page, requests, image, errors, setUploadFails: (value) => { uploadFails = value } }
}

try {
  const admin = await session(user)
  const { page, requests } = admin
  await page.goto(`${base}/#/eleves`)
  await page.getByRole('button', { name: 'Voir la photo : Alice Nga' }).click()
  const modal = page.getByRole('dialog', { name: 'Photo : Alice Nga' })
  await modal.waitFor()
  assert.equal(new URL(page.url()).hash, '#/eleves', 'photo must not navigate the row')
  assert.equal(await modal.locator('img').evaluate((img) => img.complete && img.naturalWidth === 400), true)
  await page.screenshot({ path: join(artifacts, 'desktop.png') })
  const download = page.waitForEvent('download')
  await modal.getByRole('button', { name: 'Partager', exact: true }).click()
  assert.equal((await download).suggestedFilename(), 'photo.png')
  await modal.getByRole('button', { name: 'Supprimer', exact: true }).click()
  await modal.getByRole('button', { name: 'Annuler', exact: true }).click()
  assert.equal(requests.filter((r) => r.method === 'DELETE').length, 0)
  await modal.getByLabel('Modifier', { exact: true }).click()
  admin.setUploadFails(true)
  await modal.locator('input[type=file]').setInputFiles({ name: 'photo.png', mimeType: 'image/png', buffer: admin.image })
  await modal.getByRole('alert').filter({ hasText: 'Photo refusée' }).waitFor()
  admin.setUploadFails(false)
  await modal.locator('input[type=file]').setInputFiles({ name: 'photo.png', mimeType: 'image/png', buffer: admin.image })
  await modal.waitFor({ state: 'hidden' })

  await page.goto(`${base}/#/identification/classes/7`)
  await page.getByRole('button', { name: 'Voir la photo : Alice Nga' }).click()
  await modal.getByLabel('Modifier', { exact: true }).click()
  await modal.getByRole('button', { name: 'Appareil photo', exact: true }).click()
  await page.locator('video').waitFor()
  await page.waitForFunction(() => document.querySelector('video')?.videoWidth > 0)
  await page.getByRole('button', { name: 'Prendre la photo / Take photo' }).click()
  const cameraUpload = page.waitForRequest((req) => req.method() === 'POST' && new URL(req.url()).pathname.endsWith('/eleves/1/photo'))
  await page.getByRole('button', { name: 'Valider / Confirm' }).click()
  await cameraUpload
  await page.getByRole('button', { name: 'Valider / Confirm' }).waitFor({ state: 'hidden' })
  assert.equal(requests.filter((r) => r.path === '/eleves/1/photo' && r.method === 'POST').length, 3)
  await page.getByRole('button', { name: 'Voir la photo : Alice Nga' }).click()
  await modal.getByRole('button', { name: 'Supprimer', exact: true }).click()
  await modal.getByRole('button', { name: 'Supprimer', exact: true }).filter({ hasText: 'Supprimer' }).click()
  await modal.waitFor({ state: 'hidden' })
  assert.equal(requests.filter((r) => r.method === 'DELETE').length, 1)
  assert.deepEqual(admin.errors, [])
  await admin.context.close()

  const reader = await session({ ...user, permissions: ['eleves.view'] }, { width: 360, height: 800 })
  await reader.page.goto(`${base}/#/identification/classes/7`)
  await reader.page.getByRole('button', { name: 'Voir la photo : Alice Nga' }).click()
  const readonly = reader.page.getByRole('dialog')
  assert.equal(await readonly.getByLabel('Modifier', { exact: true }).count(), 0)
  assert.equal(await readonly.getByRole('button', { name: 'Supprimer', exact: true }).count(), 0)
  const bounds = await readonly.boundingBox()
  assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= 360 && bounds.y >= 0 && bounds.y + bounds.height <= 800)
  await reader.page.screenshot({ path: join(artifacts, 'mobile.png') })
  await reader.page.keyboard.press('Escape')
  await readonly.waitFor({ state: 'hidden' })
  assert.deepEqual(reader.errors, [])
  await reader.context.close()

  const parent = await session({ ...user, roles: ['parent'], permissions: [] })
  await parent.page.goto(`${base}/#/parent`)
  await parent.page.getByRole('button', { name: 'Voir la photo : Alice Nga' }).click()
  assert.equal(new URL(parent.page.url()).hash, '#/parent')
  assert.equal(await parent.page.getByRole('dialog').getByLabel('Modifier', { exact: true }).count(), 1)
  await parent.page.getByRole('dialog').getByRole('button', { name: 'Fermer', exact: true }).click()
  await parent.page.goto(`${base}/#/parent/enfants/1`)
  await parent.page.getByRole('button', { name: 'Voir la photo : Alice Nga' }).first().click()
  const parentModal = parent.page.getByRole('dialog')
  assert.equal(await parentModal.getByRole('button', { name: 'Supprimer', exact: true }).count(), 0)
  await parentModal.getByLabel('Modifier', { exact: true }).click()
  await parentModal.locator('input[type=file]').setInputFiles({ name: 'photo.png', mimeType: 'image/png', buffer: parent.image })
  await parentModal.waitFor({ state: 'hidden' })
  assert.equal(parent.requests.filter((r) => r.path === '/parent/enfants/1/modification' && r.method === 'POST').length, 1)
  assert.equal(parent.requests.filter((r) => r.path === '/eleves/1/photo').length, 0)
  assert.deepEqual(parent.errors, [])
  await parent.context.close()
  console.log(`Photo preview checks passed. Screenshots: ${artifacts}`)
} catch (error) {
  if (activePage && !activePage.isClosed()) {
    await activePage.screenshot({ path: join(artifacts, 'failure.png') })
    console.error((await activePage.locator('body').innerText()).slice(0, 4000))
  }
  throw error
} finally { await browser.close() }
