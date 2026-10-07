import assert from 'node:assert/strict'
import { mkdir } from 'node:fs/promises'
import { join } from 'node:path'
import { chromium } from 'playwright'

const base = process.env.NOTES_TEST_URL ?? 'http://127.0.0.1:5174'
const artifacts = join(process.env.TEMP ?? '.', 'elites-sequences-notes')
await mkdir(artifacts, { recursive: true })
const browser = await chromium.launch()
const school = { id: 1, name: 'Elites Secondaire', code: 'SEC', type: 'secondaire' }

async function session(enseignant, viewport = { width: 1280, height: 900 }) {
  const user = { id: 1, name: enseignant ? 'Enseignant' : 'Direction', email: 'notes@example.test',
    school_id: 1, niveau_id: null, roles: enseignant ? [] : ['admin_ecole'], is_super_admin: false,
    est_enseignant: enseignant, est_personnel: true, ecoles_accessibles: [school], attributions: [],
    permissions: ['notes.view', 'notes.create', 'trimestres.update', 'classes.view', 'trimestres.view'] }
  const context = await browser.newContext({ viewport })
  await context.addInitScript((user) => {
    localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'notes-test', user, activeSchoolId: 1 }, version: 3 }))
    localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }))
  }, user)
  const page = await context.newPage()
  page.setDefaultTimeout(15000)
  const errors = []
  page.on('pageerror', (e) => errors.push(e.message))
  const trimestres = [1, 2, 3].map((id) => ({ id, annee_scolaire_id: 1, annee_active: true,
    ordre: id, libelle: `Trimestre ${id}`, is_active: id === 1,
    sequences: [1, 2].map((ordre) => ({ id: (id - 1) * 2 + ordre, trimestre_id: id,
      ordre, libelle: `Sequence ${(id - 1) * 2 + ordre}`, saisie_ouverte: ordre === 1 })) }))
  const writes = []
  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const url = new URL(req.url())
    const path = url.pathname.replace('/api/v1', '')
    let data = []
    if (path === '/auth/me') data = user
    else if (path === '/trimestres') data = trimestres
    else if (path === '/schools') data = [school]
    else if (path === '/classes') data = [{ id: 2, nom: '6A', school_id: 1, school }]
    else if (path === '/classe-matieres/mes-affectations') data = [{ classe_matiere_id: 3, classe_id: 2,
      classe: '6A', matiere: 'Calcul', taux_remplissage: 100 }]
    else if (path === '/classes/2/remplissage') data = { trimestre: trimestres[0], matieres: [
      { classe_matiere_id: 3, matiere: 'Calcul', enseignant: 'Enseignant', taux: 100 }] }
    else if (path === '/classes/2/matieres') data = [{ id: 3, matiere: { nom: 'Calcul' }, coefficient: 1 }]
    else if (/^\/sequences\/\d+\/saisie$/.test(path) && req.method() === 'PATCH') {
      const id = Number(path.split('/')[2])
      const sequence = trimestres.flatMap((tr) => tr.sequences).find((seq) => seq.id === id)
      sequence.saisie_ouverte = req.postDataJSON().saisie_ouverte
      writes.push({ path, body: req.postDataJSON() })
      data = sequence
    } else if (path === '/classe-matieres/3/notes') {
      if (req.method() === 'POST') {
        writes.push({ path, body: req.postDataJSON() })
        data = { saved: 1 }
      } else data = [{ eleve_id: 5, nom_complet: 'ELEVE UN', note_id: 1,
        valeur: Number(url.searchParams.get('sequence_id')) === 1 ? 8 : 9 }]
    }
    await route.fulfill({ json: { success: true, data, meta: { pagination: { total: 1, current_page: 1, per_page: 1000, last_page: 1 } } } })
  })
  return { context, page, errors, writes, trimestres }
}

async function noOverflow(page) {
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true)
}

try {
  for (const width of [1280, 390]) {
    const admin = await session(false, { width, height: 900 })
    const { page } = admin
    await page.goto(`${base}/#/remplissage`)
    const switches = page.getByRole('switch')
    await switches.last().waitFor()
    assert.equal(await switches.count(), 6)
    const first = page.getByRole('switch', { name: 'Séquence 1', exact: true })
    assert.equal(await first.isChecked(), true)
    await first.click()
    await page.waitForFunction(() => {
      const input = document.querySelector('[role="switch"]')
      return !input.disabled && !input.checked
    })
    assert.equal(await first.isChecked(), false)
    assert.deepEqual(admin.writes[0].body, { saisie_ouverte: false })
    await first.click()
    await page.waitForFunction(() => {
      const input = document.querySelector('[role="switch"]')
      return !input.disabled && input.checked
    })
    assert.equal(await first.isChecked(), true)
    await noOverflow(page)
    await page.screenshot({ path: join(artifacts, `admin-${width}.png`), fullPage: true })
    assert.deepEqual(admin.errors, [])
    await admin.context.close()
  }

  const teacher = await session(true)
  const { page } = teacher
  await page.goto(`${base}/#/enseignant/mes-matieres/3/notes`)
  await page.locator('input[type="number"]').last().waitFor()
  assert.equal(await page.locator('input[type="number"][readonly]').count(), 1)
  assert.equal(await page.locator('input[type="number"][readonly]').inputValue(), '9')
  assert.equal(await page.getByRole('switch').count(), 0)
  await page.locator('input[type="number"]:not([readonly])').fill('12')
  await page.getByRole('button', { name: /Enregistrer/ }).click()
  await page.locator('.swal2-popup').waitFor()
  assert.equal(teacher.writes.length, 1)
  assert.equal(teacher.writes[0].body.sequence_id, 1)
  assert.equal(teacher.writes[0].body.notes[0].valeur, 12)
  await page.locator('.swal2-popup').waitFor({ state: 'hidden' })
  teacher.trimestres[0].sequences[0].saisie_ouverte = false
  await page.reload()
  await page.locator('input[type="number"][readonly]').last().waitFor()
  assert.equal(await page.locator('input[type="number"][readonly]').count(), 2)
  assert.equal(await page.getByRole('button', { name: /Enregistrer/ }).isDisabled(), true)
  await noOverflow(page)
  await page.screenshot({ path: join(artifacts, 'teacher-closed.png'), fullPage: true })
  assert.deepEqual(teacher.errors, [])
  await teacher.context.close()
  console.log(`Sequences notes: admin desktop/mobile toggles and teacher readonly/save passed. Screenshots: ${artifacts}`)
} finally {
  await browser.close()
}
