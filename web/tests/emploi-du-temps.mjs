import assert from 'node:assert/strict'
import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'
import { chromium } from 'playwright'

const base = process.env.EDT_TEST_URL ?? 'http://127.0.0.1:5174'
const artifacts = join(process.env.TEMP ?? '.', 'elites-emploi-du-temps')
await mkdir(artifacts, { recursive: true })
const browser = await chromium.launch()
const schools = [
  { id: 1, name: 'Elites Secondaire', type: 'secondaire', code: 'SEC' },
  { id: 2, name: 'Elites Primaire', type: 'primaire', code: 'PRI' },
]
const profile = { id: 1, name: 'Direction', email: 'test@example.test', school_id: 1, roles: [], is_super_admin: false,
  est_enseignant: false, perimetre_borne: false, permissions: ['emploi_du_temps.view', 'emploi_du_temps.delete'], ecoles_accessibles: schools, attributions: [] }
let activePage

async function session(user = profile, scope = 1, viewport = { width: 1280, height: 900 }) {
  const context = await browser.newContext({ viewport })
  await context.addInitScript(({ user, scope }) => {
    localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'test-token', user, activeSchoolId: scope }, version: 3 }))
    localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }))
  }, { user, scope })
  const page = await context.newPage()
  page.setDefaultTimeout(15000)
  activePage = page
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))
  const classes = [
    { id: 7, nom: '6A', school_id: 1, school: schools[0], cours_planifies: 3 },
    { id: 8, nom: '6B', school_id: 1, school: schools[0], cours_planifies: 1 },
    { id: 9, nom: 'Terminale scientifique internationale', school_id: 1, school: schools[0], cours_planifies: 0 },
    { id: 10, nom: 'CM2', school_id: 2, school: schools[1], cours_planifies: 2 },
  ]
  const requests = []
  let fail = false
  let failList = false
  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const path = new URL(req.url()).pathname.replace('/api/v1', '')
    const header = req.headers()['x-school-id']
    requests.push({ method: req.method(), path, body: req.postDataJSON(), header })
    let data = []
    if (path === '/auth/me') data = user
    else if (path === '/ma-classe') data = classes.find((c) => c.school_id === Number(header)) ?? null
    else if (path === '/schools') data = schools
    else if (path === '/emploi-du-temps/classes') {
      if (failList) return route.fulfill({ status: 500, json: { message: 'Liste indisponible' } })
      data = classes.filter((c) => !header || c.school_id === Number(header))
    } else if (path === '/classes') data = classes
    else if (path === '/emploi-du-temps' && req.method() === 'DELETE') {
      if (fail) return route.fulfill({ status: 422, json: { message: 'Suppression refusée' } })
      const payload = req.postDataJSON()
      assert.equal(Object.keys(payload).length, 1, 'must target either school or classes')
      if (payload.school_id && header) assert.equal(payload.school_id, Number(header))
      let deleted = 0
      classes.forEach((c) => {
        if (payload.school_id === c.school_id || payload.classe_ids?.includes(c.id)) {
          deleted += c.cours_planifies
          c.cours_planifies = 0
        }
      })
      data = { deleted }
    } else if (/^\/classes\/\d+\/emploi-du-temps$/.test(path)) {
      const classe = classes.find((c) => c.id === Number(path.split('/')[2]))
      data = classe.cours_planifies ? [{ id: 70, jour: 1, heure_debut: '07:30', heure_fin: '08:20', salle: null,
        salle_id: null, salle_details: null, classe_matiere_id: 1, matiere: 'Mathématiques', enseignant: 'Mme Nga',
        classe_id: classe.id, classe: classe.nom, classes_associees: [], tronc_commun: false, type: 'cours', libelle: null }] : []
    } else if (/^\/classes\/\d+\/emploi-du-temps\/\d+$/.test(path) && req.method() === 'DELETE') {
      classes.find((c) => c.id === Number(path.split('/')[2])).cours_planifies = 0
      data = null
    }
    await route.fulfill({ json: { success: true, data, meta: { pagination: { total: classes.length, current_page: 1, per_page: 1000, last_page: 1 } } } })
  })
  return { context, page, requests, errors, classes, fail: (value) => { fail = value }, failList: (value) => { failList = value } }
}

async function list(page) {
  await page.goto(`${base}/#/emploi-du-temps`)
  await page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).waitFor()
}
function deletes(s) { return s.requests.filter((r) => r.path === '/emploi-du-temps' && r.method === 'DELETE') }
async function confirm(page) { await page.locator('.swal2-confirm').click() }
async function cancel(page) { await page.locator('.swal2-cancel').click() }
async function noOverflow(page) {
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'page should not overflow horizontally')
}

try {
  const admin = await session()
  const { page } = admin
  await list(page)
  assert.equal(await page.getByRole('checkbox').count(), 4)
  assert.equal(await page.getByRole('combobox').count(), 0, 'no main class select')
  await page.getByText('3 cours planifiés', { exact: true }).waitFor()
  await page.getByText('0 cours planifiés', { exact: true }).waitFor()
  await page.getByRole('checkbox', { name: 'Sélectionner la classe 6A', exact: true }).check()
  assert.equal(new URL(page.url()).hash, '#/emploi-du-temps', 'checking must not open a class')
  assert.equal(await page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).evaluate((el) => el.indeterminate), true)
  await page.getByRole('textbox').fill('6B')
  await page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).check()
  await page.getByRole('textbox').fill('')
  await page.getByText('2 classes sélectionnées', { exact: true }).waitFor()
  await noOverflow(page)
  await page.screenshot({ path: join(artifacts, 'desktop-list.png') })
  await page.getByRole('button', { name: 'Supprimer la sélection', exact: true }).click()
  await cancel(page)
  assert.equal(deletes(admin).length, 0)
  admin.fail(true)
  await page.getByRole('button', { name: 'Supprimer la sélection', exact: true }).click()
  await confirm(page)
  await page.getByText('Suppression refusée', { exact: true }).waitFor()
  await page.getByText('2 classes sélectionnées', { exact: true }).waitFor()
  admin.fail(false)
  await page.getByRole('button', { name: 'Supprimer la sélection', exact: true }).click()
  await confirm(page)
  await page.getByRole('button', { name: 'Supprimer la sélection', exact: true }).waitFor({ state: 'hidden' })
  await page.getByRole('button', { name: "Ouvrir l'emploi du temps de 6A", exact: true }).filter({ hasText: '0 cours planifiés' }).waitFor()
  assert.deepEqual(deletes(admin).at(-1).body, { classe_ids: [7, 8] })
  assert.ok(admin.requests.filter((r) => r.path === '/emploi-du-temps/classes').length >= 2)
  await admin.context.close()

  const grid = await session()
  await list(grid.page)
  await grid.page.getByRole('button', { name: "Ouvrir l'emploi du temps de 6A", exact: true }).click()
  assert.equal(new URL(grid.page.url()).hash, '#/emploi-du-temps?classe=7')
  await grid.page.getByText('Mathématiques', { exact: true }).waitFor()
  await grid.page.goBack()
  await grid.page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).waitFor()
  await grid.page.goForward()
  await grid.page.getByText('Mathématiques', { exact: true }).waitFor()
  await grid.page.getByRole('button', { name: 'Retour aux classes', exact: true }).click()
  await grid.page.getByRole('button', { name: 'Supprimer les emplois du temps', exact: true }).click()
  await grid.page.locator('.swal2-html-container').filter({ hasText: 'Elites Secondaire' }).waitFor()
  await grid.page.locator('.swal2-html-container').filter({ hasText: 'séances déjà générées sont conservées' }).waitFor()
  await grid.page.screenshot({ path: join(artifacts, 'school-confirmation.png') })
  await cancel(grid.page)
  assert.equal(deletes(grid).length, 0)
  await grid.page.getByRole('button', { name: 'Supprimer les emplois du temps', exact: true }).click()
  await confirm(grid.page)
  await grid.page.getByRole('button', { name: "Ouvrir l'emploi du temps de 6A", exact: true }).filter({ hasText: '0 cours planifiés' }).waitFor()
  assert.deepEqual(deletes(grid)[0].body, { school_id: 1 })
  assert.equal(grid.classes.find((c) => c.id === 10).cours_planifies, 2, 'other school untouched')
  assert.deepEqual(grid.errors, [])
  await grid.context.close()

  const slot = await session()
  await slot.page.goto(`${base}/#/emploi-du-temps?classe=7`)
  await slot.page.getByText('Mathématiques', { exact: true }).waitFor()
  const countRequests = slot.requests.filter((r) => r.path === '/emploi-du-temps/classes').length
  await slot.page.getByTitle('Supprimer le créneau', { exact: true }).click()
  await confirm(slot.page)
  await slot.page.getByText('Mathématiques', { exact: true }).waitFor({ state: 'hidden' })
  await slot.page.getByRole('button', { name: 'Retour aux classes', exact: true }).click()
  await slot.page.getByRole('button', { name: "Ouvrir l'emploi du temps de 6A", exact: true }).filter({ hasText: '0 cours planifiés' }).waitFor()
  assert.ok(slot.requests.filter((r) => r.path === '/emploi-du-temps/classes').length > countRequests, 'slot mutation refreshes counts')
  assert.deepEqual(slot.errors, [])
  await slot.context.close()

  const aggregate = await session({ ...profile, is_super_admin: true, school_id: null }, null)
  await list(aggregate.page)
  assert.equal(await aggregate.page.getByRole('checkbox').count(), 5)
  await aggregate.page.getByRole('button', { name: 'Supprimer les emplois du temps', exact: true }).click()
  const modal = aggregate.page.getByRole('dialog', { name: "Choisir l'école concernée" })
  await modal.getByRole('button', { name: 'Elites Primaire', exact: true }).click()
  await aggregate.page.locator('.swal2-html-container').filter({ hasText: 'Elites Primaire' }).waitFor()
  await confirm(aggregate.page)
  await aggregate.page.getByRole('button', { name: "Ouvrir l'emploi du temps de CM2", exact: true }).filter({ hasText: '0 cours planifiés' }).waitFor()
  assert.deepEqual(deletes(aggregate)[0].body, { school_id: 2 })
  assert.equal(aggregate.classes[0].cours_planifies, 3)
  assert.equal(deletes(aggregate)[0].header, undefined)
  assert.deepEqual(aggregate.errors, [])
  await aggregate.context.close()

  const teacher = await session({ ...profile, school_id: 2, est_enseignant: true, perimetre_borne: true,
    permissions: ['emploi_du_temps.view'] }, 2)
  await teacher.page.goto(`${base}/#/emploi-du-temps?classe=7`)
  await teacher.page.getByText('CM2', { exact: true }).waitFor()
  await teacher.page.getByText('Mathématiques', { exact: true }).waitFor()
  assert.equal(await teacher.page.getByRole('checkbox').count(), 0)
  assert.ok(teacher.requests.some((r) => r.path === '/classes/10/emploi-du-temps'))
  assert.ok(!teacher.requests.some((r) => r.path === '/classes/7/emploi-du-temps'), 'teacher URL cannot override assigned class')
  assert.equal(await teacher.page.getByText('07:00', { exact: true }).count(), 1, 'primary grid has half-hour periods')
  assert.deepEqual(teacher.errors, [])
  await teacher.context.close()

  const restricted = await session({ ...profile, perimetre_borne: true })
  await list(restricted.page)
  assert.equal(await restricted.page.getByRole('button', { name: 'Supprimer les emplois du temps', exact: true }).count(), 0)
  await restricted.page.getByRole('checkbox', { name: 'Sélectionner la classe 6A', exact: true }).check()
  assert.equal(await restricted.page.getByRole('button', { name: 'Supprimer la sélection', exact: true }).count(), 1)
  await restricted.context.close()

  const reader = await session({ ...profile, permissions: ['emploi_du_temps.view'] }, 1, { width: 360, height: 800 })
  await list(reader.page)
  await reader.page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).check()
  assert.equal(await reader.page.getByRole('button', { name: /Supprimer/ }).count(), 0)
  await noOverflow(reader.page)
  await reader.page.screenshot({ path: join(artifacts, 'mobile-list.png') })
  await reader.page.getByRole('button', { name: "Ouvrir l'emploi du temps de 6A", exact: true }).click()
  await reader.page.getByText('Mathématiques', { exact: true }).waitFor()
  await noOverflow(reader.page)
  await reader.page.screenshot({ path: join(artifacts, 'mobile-grid.png') })
  assert.deepEqual(reader.errors, [])
  await reader.context.close()

  const mobileAdmin = await session(profile, 1, { width: 360, height: 800 })
  await list(mobileAdmin.page)
  await mobileAdmin.page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).check()
  await noOverflow(mobileAdmin.page)
  await mobileAdmin.page.screenshot({ path: join(artifacts, 'mobile-admin.png') })
  await mobileAdmin.context.close()

  const retry = await session()
  retry.failList(true)
  await retry.page.goto(`${base}/#/emploi-du-temps`)
  await retry.page.getByText('Liste indisponible', { exact: true }).waitFor()
  retry.failList(false)
  await retry.page.getByRole('button', { name: 'Réessayer', exact: true }).click()
  await retry.page.getByRole('checkbox', { name: 'Toutes les classes', exact: true }).waitFor()
  await retry.context.close()
  console.log(`Timetable web checks passed. Screenshots: ${artifacts}`)
} catch (error) {
  if (activePage && !activePage.isClosed()) {
    await activePage.screenshot({ path: join(artifacts, 'failure.png') })
    console.error((await activePage.locator('body').innerText()).slice(0, 4000))
  }
  throw error
} finally {
  await browser.close()
}
