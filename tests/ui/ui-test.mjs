// Drives the real PromoTrack forms in Chrome: types into inputs, picks
// options, clicks the buttons a person clicks, and reads back what the
// page then says. Usage: node ui-test.mjs <baseUrl> <outDir>
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';

const BASE = process.argv[2] ?? 'http://localhost:8080';
const OUT = process.argv[3] ?? './shots';
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const STAMP = Date.now().toString().slice(-6);
const ADMIN = ['superadmin@promotrack.local', 'ChangeMe!12345'];
fs.mkdirSync(OUT, { recursive: true });

const results = [];

function monthsAgo(n) {
    const d = new Date();
    d.setMonth(d.getMonth() - n);
    const p = (x) => String(x).padStart(2, '0');
    return { iso: `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`, typed: `${p(d.getMonth() + 1)}${p(d.getDate())}${d.getFullYear()}` };
}

async function settle(page) {
    // The app shell shows a 3s "please wait" overlay; a click before it
    // clears lands on the overlay, not the button.
    await page.waitForFunction(() => {
        const l = document.querySelector('.page-loader');
        return !l || getComputedStyle(l).visibility === 'hidden';
    }, { timeout: 10000 });
}

async function typeDate(page, name, n) {
    const d = monthsAgo(n);
    await page.focus(`[name="${name}"]`);
    await page.keyboard.type(d.typed);
    const got = await page.$eval(`[name="${name}"]`, (el) => el.value);
    if (got !== d.iso) throw new Error(`${name}: typed ${d.typed}, field holds "${got}", expected ${d.iso}`);
    return d.iso;
}

async function pick(page, name, ...needles) {
    const value = await page.$eval(`[name="${name}"]`, (el, needles) => {
        const o = [...el.options].find((o) => needles.every((n) => o.textContent.includes(n)));
        return o ? o.value : null;
    }, needles);
    if (!value) throw new Error(`No option matching ${needles.join(' + ')} in ${name}`);
    await page.select(`[name="${name}"]`, value);
}

async function submit(page, selector = 'main button[type="submit"].btn-primary, form button[type="submit"]') {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click(selector)]);
}

async function login(page, [email, password]) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('#emailInput', email);
    await page.type('#passwordInput', password);
    await submit(page, 'button.auth-submit');
}

async function readProfile(page) {
    return page.evaluate(() => {
        const dd = (label) => [...document.querySelectorAll('dt')].find((d) => d.textContent.trim() === label)?.nextElementSibling?.innerText.trim();
        const row = document.querySelector('table tbody tr td:nth-child(2)');
        return {
            flash: document.querySelector('.alert')?.innerText.replace(/\s+/g, ' ').trim(),
            badge: dd('Promotion Status'),
            tig: row?.innerText.replace(/\s+/g, ' ').trim(),
            errors: [...document.querySelectorAll('.invalid-feedback, .text-danger')].map((e) => e.innerText.trim()).filter(Boolean),
        };
    });
}

async function staffAdd(page, label, { rank, track, monthsInGrade, expect }) {
    const number = `RSV-UI-${STAMP}-${results.length}`;
    await page.goto(`${BASE}/reservists/create`, { waitUntil: 'networkidle0' });
    await settle(page);
    await page.type('[name="reservist_number"]', number);
    await page.type('[name="afp_serial_number"]', `UI${STAMP}${results.length}`);
    await page.type('[name="first_name"]', 'Uitest');
    await page.type('[name="last_name"]', label.replace(/[^A-Za-z ]/g, ''));
    await pick(page, 'organizational_unit_id', 'Sample Reserve Unit A');
    await pick(page, 'company_unit_id', 'Alpha Company');
    await pick(page, 'current_rank_id', rank);
    await pick(page, 'education_track_id', track);
    await typeDate(page, 'date_of_enlistment', monthsInGrade + 24);
    const dlp = await typeDate(page, 'date_of_last_promotion', monthsInGrade);
    await submit(page, 'form button[type="submit"]');
    await settle(page);
    const flash = await page.$eval('.alert', (e) => e.innerText.replace(/\s+/g, ' ').trim()).catch(() => null);

    // Back on the roster: search for the new record, read its badge there,
    // then open it with the row's View button.
    await page.type('[name="q"]', number);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.keyboard.press('Enter')]);
    await settle(page);
    const listBadge = await page.$eval('tbody tr .badge', (e) => e.innerText.trim());
    await page.screenshot({ path: `${OUT}/${label}-list.png` });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('tbody tr a[title="View"]')]);
    await settle(page);

    const r = await readProfile(page);
    await page.screenshot({ path: `${OUT}/${label}.png`, fullPage: true });
    results.push({ path: 'Staff: Add Reservist', label, input: `${rank} / ${track} / last promoted ${dlp}`, expect, ...r, flash, listBadge });
    return page.url();
}

async function staffEdit(page, url, label, { monthsInGrade, expect }) {
    await page.goto(`${url}/edit`, { waitUntil: 'networkidle0' });
    await settle(page);
    await page.$eval('[name="date_of_last_promotion"]', (el) => { el.value = ''; });
    const dlp = await typeDate(page, 'date_of_last_promotion', monthsInGrade);
    await submit(page, 'form button[type="submit"]');
    await settle(page);
    const r = await readProfile(page);
    await page.screenshot({ path: `${OUT}/${label}.png`, fullPage: true });
    results.push({ path: 'Staff: Edit Reservist', label, input: `last promotion changed to ${dlp}`, expect, ...r });
}

async function staffReassess(page, url, label, expect) {
    await page.goto(url, { waitUntil: 'networkidle0' });
    await settle(page);
    await submit(page, `form[action$="/assessments"] button[type="submit"]`);
    await settle(page);
    const r = await readProfile(page);
    await page.screenshot({ path: `${OUT}/${label}.png`, fullPage: true });
    results.push({ path: 'Staff: Run Eligibility Assessment', label, input: url.replace(BASE, ''), expect, ...r });
}

async function signUp(browser, label, { rank, track, monthsInGrade, expect }) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto(`${BASE}/register`, { waitUntil: 'networkidle0' });
    await page.type('[name="first_name"]', 'Signup');
    await page.type('[name="last_name"]', label.replace(/[^A-Za-z ]/g, ''));
    await page.type('[name="afp_serial_number"]', `SU${STAMP}`);
    await pick(page, 'organizational_unit_id', 'Sample Reserve Unit A');
    await pick(page, 'company_unit_id', 'Alpha Company', 'Unit A');
    await pick(page, 'current_rank_id', rank);
    await typeDate(page, 'date_of_enlistment', monthsInGrade + 24);
    const dlp = await typeDate(page, 'date_of_last_promotion', monthsInGrade);
    await pick(page, 'education_track_id', track);
    await pick(page, 'is_student', 'No');
    await page.type('[name="email"]', `signup${STAMP}@promotrack.local`);
    await page.type('[name="username"]', `signup${STAMP}`);
    await page.type('[name="password"]', 'Kalabaw!Bundok77');
    await page.type('[name="password_confirm"]', 'Kalabaw!Bundok77');
    await submit(page, 'button.auth-submit');
    await page.goto(`${BASE}/my/promotion`, { waitUntil: 'networkidle0' });
    await settle(page);
    const r = await page.evaluate(() => {
        const card = [...document.querySelectorAll('.card')].find((c) => c.querySelector('.card-header')?.textContent.includes('Promotion Standing'));
        return { badge: card?.querySelector('.badge')?.innerText.trim(), tig: card?.querySelector('p.small')?.innerText.trim(), flash: document.querySelector('.alert-danger')?.innerText.trim() };
    });
    await page.screenshot({ path: `${OUT}/${label}.png` });
    results.push({ path: 'Reservist: Sign up -> My Promotion', label, input: `${rank} / ${track} / last promoted ${dlp}`, expect, ...r });
    await ctx.close();
}

const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--window-size=1440,900'] });
try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await login(page, ADMIN);

    // REASSESS="6:Eligible,5:Not Yet Eligible" -> only press Run Eligibility
    // Assessment on existing records (used on live, where test records
    // would pollute the client's data).
    if (process.env.REASSESS) {
        for (const pair of process.env.REASSESS.split(',')) {
            const [id, expect] = pair.split(':');
            await staffReassess(page, `${BASE}/reservists/${id}`, `reassess-${id}`, expect);
        }
    } else {
    await staffAdd(page, '1-add-eligible', { rank: 'Corporal', track: 'ROTC Professional', monthsInGrade: 36, expect: 'Eligible' });
    const notYet = await staffAdd(page, '2-add-not-yet', { rank: 'Corporal', track: 'General', monthsInGrade: 60, expect: 'Not Yet Eligible' });
    await staffAdd(page, '3-add-overdue', { rank: 'Sergeant', track: 'ROTC Professional', monthsInGrade: 60, expect: 'Overdue' });
    await staffEdit(page, notYet, '4-edit-to-eligible', { monthsInGrade: 84, expect: 'Eligible' });
    if (process.argv[4]) await staffReassess(page, `${BASE}/reservists/${process.argv[4]}`, '5-reassess-existing', process.argv[5] ?? 'Eligible');
    await signUp(browser, '6-signup-eligible', { rank: 'Sergeant', track: 'ROTC Professional', monthsInGrade: 48, expect: 'Eligible' });
    }
} finally {
    await browser.close();
    console.log(JSON.stringify(results, null, 2));
    const expected = process.env.REASSESS ? process.env.REASSESS.split(',').length : (process.argv[4] ? 6 : 5);
    const ok = results.length === expected
        && results.every((r) => r.badge === r.expect && (r.listBadge === undefined || r.listBadge === r.expect));
    console.log(ok ? `ALL ${results.length} UI CASES MATCH` : 'MISMATCH — see above');
}
