import { chromium } from 'playwright'

const dir = 'C:\\Users\\SAMUEL TCHIOFFOUO\\AppData\\Local\\Temp\\claude\\c--laragon-www-elites-school\\1f55a31d-19d5-47d7-bd99-4d8f3852c60b\\scratchpad'
const shot = (page, name) => page.screenshot({ path: `${dir}\\${name}.png` })

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } })
const erreurs = []
page.on('console', (msg) => { if (msg.type() === 'error') erreurs.push(msg.text()) })
page.on('pageerror', (err) => erreurs.push(String(err)))

await page.goto('http://localhost:5173')
await page.waitForSelector('input[type="email"], input[name="email"]', { timeout: 15000 })
await shot(page, '00-login-page')
await page.fill('input[type="email"], input[name="email"]', 'admin@elites-school.test')
await page.fill('input[type="password"], input[name="password"]', 'password')
await page.click('button[type="submit"]')
await page.waitForTimeout(2000)
await shot(page, '01-after-login')
console.log('URL_AFTER_LOGIN:', page.url())

await page.goto('http://localhost:5173/personnel')
await page.waitForTimeout(1500)
await shot(page, '02-personnel-page')
console.log('URL_PERSONNEL:', page.url())

const boutonAjouter = page.getByRole('button', { name: /ajouter un membre/i })
await boutonAjouter.waitFor({ state: 'visible', timeout: 15000 })
await boutonAjouter.click()
await page.waitForTimeout(500)
await shot(page, '03-modal-open')

// Ouvrir le select "Département" (bouton stylé, pas un <select> natif)
const selectDepartement = page.locator('label:has-text("DÉPARTEMENT") button')
await selectDepartement.waitFor({ state: 'visible', timeout: 10000 })
await selectDepartement.click()
await page.waitForTimeout(300)
await shot(page, '04-select-open')

// Taper dans la recherche pour filtrer les options
const rechercheInput = page.locator('input[placeholder="Rechercher…"]')
await rechercheInput.waitFor({ state: 'visible', timeout: 5000 })
await rechercheInput.fill('a')
await page.waitForTimeout(300)
await shot(page, '05-select-filtered')

console.log('OPTIONS_VISIBLES:', await page.locator('[role="option"]').allTextContents())

// Choisir la première option filtrée
await page.locator('[role="option"]').first().click()
await page.waitForTimeout(300)
await shot(page, '06-select-chosen')
console.log('VALEUR_AFFICHEE:', await selectDepartement.textContent())

// Soumission complète du formulaire : preuve que la valeur choisie via le
// select personnalisé est bien envoyée par react-hook-form au backend.
const nomAleatoire = `TestSelect${Date.now()}`
await page.fill('label:has-text("PRÉNOM") input', 'Playwright')
await page.fill('label:has-text("NOM") input', nomAleatoire)
await page.fill('label:has-text("FONCTION") input', 'Testeur QA')
await page.click('button:has-text("Enregistrer")')
await page.waitForTimeout(1500)
await shot(page, '07-after-submit')
console.log('LIGNE_CREEE_VISIBLE:', await page.getByText(nomAleatoire).count())
console.log('CONSOLE_ERRORS_END:', JSON.stringify(erreurs))

console.log('CONSOLE_ERRORS_SO_FAR:', JSON.stringify(erreurs))

await browser.close()
