import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage();

const requests = [];
page.on('request', (req) => {
  if (req.url().includes('/tuteurs')) {
    requests.push(req.url());
  }
});
page.on('console', (msg) => console.log('CONSOLE:', msg.type(), msg.text()));
page.on('pageerror', (err) => console.log('PAGEERROR:', err.message));

await page.goto('http://127.0.0.1:5173/login', { waitUntil: 'networkidle' });
await page.waitForTimeout(1000);

// Try to find login inputs
const inputs = await page.locator('input').all();
console.log('Number of inputs on login page:', inputs.length);
for (const inp of inputs) {
  console.log('input:', await inp.getAttribute('name'), await inp.getAttribute('type'), await inp.getAttribute('placeholder'));
}

await page.screenshot({ path: 'C:/Users/SAMUEL TCHIOFFOUO/AppData/Local/Temp/claude/c--laragon-www-elites-school/ee2791af-ba56-4fc5-84a8-bc6b22099775/scratchpad/login.png' });

await browser.close();
