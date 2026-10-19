import assert from 'node:assert/strict'
import { chromium } from 'playwright'

const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL })
const base = process.env.JOURNEE_TEST_URL ?? 'http://127.0.0.1:5184'

async function session({ cycle = 'secondaire', teacher = true, empty = false } = {}) {
  const school = { id: 1, name: 'Elites Test', type: cycle, code: 'TEST' }
  const user = { id: 3, name: 'Enseignant', email: 'prof@example.test', school_id: 1, roles: [], is_super_admin: false,
    est_enseignant: teacher, est_personnel: true, methode_validation_seance: 'libre', perimetre_borne: false,
    permissions: ['appel.saisir', 'classes.view', 'discipline.view', 'justifications.view'], ecoles_accessibles: [school], attributions: [] }
  const context = await browser.newContext()
  await context.addInitScript((user) => {
    localStorage.setItem('elites-school-auth', JSON.stringify({ state: { token: 'test-token', user, activeSchoolId: 1 }, version: 3 }))
    localStorage.setItem('elites-school-ui', JSON.stringify({ state: { locale: 'fr', sidebarOpen: false }, version: 0 }))
  }, user)
  const page = await context.newPage()
  page.setDefaultTimeout(20000)
  page.setDefaultNavigationTimeout(60000)
  const errors = []
  page.on('pageerror', (e) => errors.push(e.message))
  let fail = false
  const requests = []
  const feuille = { seance: { id: 10, date: '2026-10-19', heure_debut: '07:00', heure_fin: '18:00', statut: 'prevue',
    contenu: null, observations: null, donnees_personnalisees: {}, methode_validation_seance: 'libre' },
    lecons: empty ? [] : [{ id: 12, titre: 'Les fractions', chemin: '', sequence: null, faite_aujourdhui: false, deja_traitee: false, validee_par: null }], appel: [], champs_personnalises: [] }
  await page.route('**/api/v1/**', async (route) => {
    const req = route.request()
    const path = new URL(req.url()).pathname.replace('/api/v1', '')
    const method = req.method()
    const payload = req.postDataJSON()
    requests.push({ path, method, payload })
    let data = []
    if (path === '/auth/me') data = user
    else if (path === '/schools') data = [school]
    else if (path === '/ma-journee') data = [{ classe_matiere_id: 5, classe_id: 7, classe: 'Classe A', matiere: 'Maths', heure_debut: '07:00', heure_fin: '18:00' }]
    else if (path === '/ma-journee/couverture-periodes') {
      const stats = { heures_prevues: 0, heures_realisees: 0, taux: 0, seances_en_retard: 0 }
      data = Object.fromEntries(['jour', 'semaine', 'mois', 'annee'].map((key) => [key, stats]))
    } else if (path === '/ma-journee/5' && method === 'GET') data = feuille
    else if (path === '/ma-journee/5' && method === 'POST') {
      if (fail) return route.fulfill({ status: 422, json: { message: 'Validation refusée pour le test.' } })
      const mapping = new Map()
      for (const action of payload.actions_lecons ?? []) {
        const id = 20 + feuille.lecons.length
        mapping.set(action.lecon_id, id)
        feuille.lecons.push({ id, titre: action.fiche.titre, chemin: '', sequence: null, faite_aujourdhui: false, deja_traitee: false, validee_par: null })
      }
      const selected = payload.lecons.map((id) => mapping.get(id) ?? id)
      feuille.lecons.forEach((l) => { l.faite_aujourdhui = selected.includes(l.id) })
      feuille.seance.statut = 'effectuee'
      data = feuille
    } else if (path === '/classes/7' || path === '/ma-classe') data = { id: 7, nom: 'Classe A', school_id: 1, school, niveau: null, effectif: 0 }
    await route.fulfill({ json: { success: true, data } })
  })
  return { context, page, requests, errors, fail: (value) => { fail = value } }
}

try {
  const s = await session()
  await s.page.goto(`${base}/#/ma-journee`)
  const save = s.page.getByRole('button', { name: 'Enregistrer', exact: true })
  const lesson = s.page.getByRole('checkbox', { name: 'Les fractions', exact: true })
  await lesson.waitFor()
  assert.equal(await save.isDisabled(), true)
  await s.page.getByLabel('Précisions sur la leçon (facultatif)', { exact: true }).fill('Travail effectué')
  assert.equal(await save.isDisabled(), true, 'Free text alone cannot validate a class')
  await lesson.check()
  assert.equal(await save.isEnabled(), true)
  await lesson.uncheck()
  assert.equal(await save.isDisabled(), true)
  await lesson.check()
  await save.click()
  await s.page.getByText('Journée enregistrée.', { exact: true }).waitFor()
  assert.deepEqual(s.requests.find((r) => r.method === 'POST').payload.lecons, [12])
  assert.deepEqual(s.errors, [])
  await s.context.close()

  const empty = await session({ empty: true })
  await empty.page.goto(`${base}/#/ma-journee`)
  const title = empty.page.getByLabel('Titre de la nouvelle leçon', { exact: true })
  await title.waitFor()
  const add = empty.page.getByRole('button', { name: 'Ajouter et marquer faite', exact: true })
  await title.fill('   ')
  assert.equal(await add.isDisabled(), true)
  await title.fill('Les nombres décimaux')
  await add.click()
  const draft = empty.page.getByRole('checkbox', { name: 'Les nombres décimaux', exact: true })
  assert.equal(await draft.isChecked(), true)
  const saveEmpty = empty.page.getByRole('button', { name: 'Enregistrer', exact: true })
  empty.fail(true)
  await saveEmpty.click()
  await empty.page.getByText('Validation refusée pour le test.', { exact: true }).waitFor()
  assert.equal(await draft.isChecked(), true)
  empty.fail(false)
  await saveEmpty.click()
  await empty.page.getByTitle('Retirer cette nouvelle leçon', { exact: true }).waitFor({ state: 'hidden' })
  const writes = empty.requests.filter((r) => r.method === 'POST')
  assert.equal(writes[0].payload.actions_lecons[0].operation_id, writes[1].payload.actions_lecons[0].operation_id, 'Retry preserves the operation ID')
  assert.deepEqual(writes[1].payload.lecons, [-1])
  assert.equal(writes[1].payload.actions_lecons[0].fiche.titre, 'Les nombres décimaux')
  assert.deepEqual(empty.errors, [])
  await empty.context.close()

  for (const cycle of ['secondaire', 'primaire', 'maternelle']) {
    const teacher = await session({ cycle })
    await teacher.page.goto(`${base}/#/ma-classe`)
    await teacher.page.getByRole('heading', { name: 'Classe A', exact: true }).waitFor()
    assert.equal(await teacher.page.getByRole('button', { name: 'Discipline', exact: true }).count(), 0)
    assert.equal(await teacher.page.locator('a[href="#/justifications"]').count(), 0)
    await teacher.page.goto(`${base}/#/justifications`)
    await teacher.page.waitForURL((url) => url.hash !== '#/justifications')
    assert.equal(teacher.requests.some((r) => r.path === '/justifications'), false)
    assert.deepEqual(teacher.errors, [])
    await teacher.context.close()
  }
  const admin = await session({ teacher: false })
  await admin.page.goto(`${base}/#/classes/7`)
  await admin.page.getByRole('button', { name: 'Discipline', exact: true }).waitFor()
  await admin.page.goto(`${base}/#/justifications`)
  await admin.page.getByRole('heading', { name: /Justifications/ }).waitFor()
  assert.equal(new URL(admin.page.url()).hash, '#/justifications')
  assert.deepEqual(admin.errors, [])
  await admin.context.close()
  console.log('Course validation and teacher absence access checks passed.')
} finally {
  await browser.close()
}
