// Drives the real PromoTrack forms in Chrome: types into inputs, picks
// options, clicks the buttons a person clicks, and reads back what the
// page then says. Also walks the student reservist portal (sign-up, ROTC
// E-1 to E-4, photo, dashboard) and the Master Sergeant notice. Usage: node ui-test.mjs <baseUrl> <outDir>
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
    // Hidden options are ones the form has filtered out (another CDC's
    // companies, another category's ranks); a person cannot pick those.
    const value = await page.$eval(`[name="${name}"]`, (el, needles) => {
        const o = [...el.options].find((o) => !o.hidden && needles.every((n) => o.textContent.includes(n)));
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

// Fills the client's sign-up form (revision of 2026-11-09). The education
// track is no longer asked: it follows from the attainment, so "bachelor"
// lands on the Completed 1st Year College column of Table 3.
async function fillSignUp(page, { first, last, serial, email, username, rankCategory, rank, attainment, degree, yearLevel, isStudent, enlistedMonthsAgo, promotedMonthsAgo }) {
    await page.goto(`${BASE}/register`, { waitUntil: 'networkidle0' });
    await page.type('[name="first_name"]', first);
    await page.type('[name="middle_name"]', 'Santos');
    await page.type('[name="last_name"]', last);
    await page.type('[name="afp_serial_number"]', serial);
    await pick(page, 'organizational_unit_id', '1502 CDC');
    await pick(page, 'company_unit_id', 'Alpha Company');
    await page.click('#sex_male');
    await typeDate(page, 'date_of_birth', 12 * 22);
    await page.type('[name="contact_number"]', '09171234567');
    await page.type('[name="email"]', email);
    await page.type('[name="address"]', 'San Francisco, Agusan del Sur');
    await page.click(`#attain_${attainment}`);
    if (yearLevel) await page.select('[name="student_year_level"]', yearLevel);
    if (degree) await page.type('[name="degree_details"]', degree);
    await page.select('[name="rank_category"]', rankCategory);
    await pick(page, 'current_rank_id', rank);
    await typeDate(page, 'date_of_enlistment', enlistedMonthsAgo);
    const dlp = promotedMonthsAgo !== undefined ? await typeDate(page, 'date_of_last_promotion', promotedMonthsAgo) : null;
    await page.select('[name="is_student"]', isStudent ? '1' : '0');
    await page.type('[name="username"]', username);
    await page.type('[name="password"]', 'Kalabaw!Bundok77');
    await page.type('[name="password_confirm"]', 'Kalabaw!Bundok77');
    return dlp;
}

async function signUp(browser, label, { rank, monthsInGrade, expect }) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const dlp = await fillSignUp(page, {
        first: 'Signup', last: label.replace(/[^A-Za-z ]/g, ''), serial: `SU${STAMP}`,
        email: `signup${STAMP}@promotrack.local`, username: `signup${STAMP}`,
        rankCategory: 'enlisted', rank, attainment: 'bachelor', degree: 'BS Criminology', isStudent: false,
        enlistedMonthsAgo: monthsInGrade + 24, promotedMonthsAgo: monthsInGrade,
    });
    await submit(page, 'button.reg-submit');
    await page.goto(`${BASE}/my/promotion`, { waitUntil: 'networkidle0' });
    await settle(page);
    const r = await page.evaluate(() => {
        const card = [...document.querySelectorAll('.card')].find((c) => c.querySelector('.card-header')?.textContent.includes('Promotion Standing'));
        return { badge: card?.querySelector('.badge')?.innerText.trim(), tig: card?.querySelector('p.small')?.innerText.trim(), flash: document.querySelector('.alert-danger')?.innerText.trim() };
    });
    await page.screenshot({ path: `${OUT}/${label}.png` });
    results.push({ path: 'Reservist: Sign up -> My Documents', label, input: `${rank} / Bachelor's / last promoted ${dlp}`, expect, ...r });
    await ctx.close();
}

// The student reservist portal end to end: sign up as an undergraduate
// MS32 cadet, fill E-1 to E-4 with the buttons the client listed, upload a
// photo, and read back the dashboard. Each check is one result row whose
// `badge` is what the page showed.
async function studentPortal(browser, label) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const push = (step, expect, badge, extra = {}) => results.push({ path: `Student portal: ${step}`, label, expect, badge, ...extra });

    await fillSignUp(page, {
        first: 'Cadet', last: 'Portal', serial: `ST${STAMP}`,
        email: `student${STAMP}@promotrack.local`, username: `student${STAMP}`,
        rankCategory: 'rotc', rank: 'MS32', attainment: 'undergraduate', yearLevel: '2nd Year 2nd Sem', isStudent: true,
        enlistedMonthsAgo: 18,
    });
    push('summary shows the middle initial only', 'Dela Cruz style', (await page.$eval('#sumName', (e) => e.innerText)).endsWith('Cadet S.') ? 'Dela Cruz style' : await page.$eval('#sumName', (e) => e.innerText));
    await page.screenshot({ path: `${OUT}/${label}-signup.png`, fullPage: true });
    await submit(page, 'button.reg-submit');
    push('sign-up lands on the dashboard', '/my/dashboard', new URL(page.url()).pathname.replace('/index.php', ''));

    async function saveStage(code, act) {
        await page.goto(`${BASE}/my/rotc`, { waitUntil: 'networkidle0' });
        await settle(page);
        await act(`#stage-${code}`);
        await submit(page, `#stage-${code} button[type="submit"]`);
        return page.$eval('.alert', (e) => e.innerText.trim()).catch(() => '');
    }
    const choose = (stage, id) => page.click(`${stage} label[for="${id}"]`);
    push('save E-1', 'E-1 saved.', (await saveStage('E1', (s) => choose(s, 'E1_classification_ready_reserve'))).replace(/\s*×?$/, ''));
    push('save E-2', 'E-2 saved.', (await saveStage('E2', async (s) => { await page.click('#E2_coqc_ms21'); await page.click('#E2_coqc_ms22'); await choose(s, 'E2_classification_ready_reserve'); })).replace(/\s*×?$/, ''));
    push('save E-3', 'E-3 saved.', (await saveStage('E3', (s) => choose(s, 'E3_training_result_complete'))).replace(/\s*×?$/, ''));
    push('save E-4', 'E-4 saved.', (await saveStage('E4', async (s) => { await choose(s, 'E4_recommendation_recommended'); await choose(s, 'E4_classification_ready_reserve'); })).replace(/\s*×?$/, ''));

    // A photo: any PNG will do, so use a screenshot of this very page.
    await page.goto(`${BASE}/my/profile`, { waitUntil: 'networkidle0' });
    await settle(page);
    const photoPath = `${OUT}/${label}-photo-source.png`;
    await page.screenshot({ path: photoPath, clip: { x: 0, y: 0, width: 400, height: 400 } });
    const input = await page.$('#photoInput');
    await input.uploadFile(photoPath);
    await submit(page, 'form[action$="/my/profile/photo"] button[type="submit"]');
    push('upload a profile photo', 'Your photo has been updated.', await page.$eval('.alert', (e) => e.innerText.trim().replace(/\s*×?$/, '')).catch(() => ''));

    await page.goto(`${BASE}/my/dashboard`, { waitUntil: 'networkidle0' });
    await settle(page);
    const dash = await page.evaluate(() => ({
        status: document.querySelector('.status-title')?.innerText.trim(),
        rank: document.querySelector('.tile-green .tile-value-sm')?.innerText.trim(),
        photo: !!document.querySelector('.portal-avatar img') && document.querySelector('.portal-avatar img').naturalWidth > 0,
        quickAccess: document.body.innerText.includes('Quick Access'),
    }));
    await page.screenshot({ path: `${OUT}/${label}-dashboard.png`, fullPage: true });
    push('dashboard status', 'On Track for E-4', dash.status);
    push('dashboard cadet rank', 'CDT CPT 2CL', dash.rank);
    push('dashboard shows the photo', 'yes', dash.photo ? 'yes' : 'no');
    push('no Quick Access card', 'absent', dash.quickAccess ? 'present' : 'absent');
    await ctx.close();
}

// A 1st Year student (added 2026-11-11): signs up as an MS1 cadet, lands on
// E-1, and shows up in the admin's MS1 folder.
async function firstYearStudent(browser, adminPage, label) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const push = (step, expect, badge) => results.push({ path: `1st Year student: ${step}`, label, expect, badge });
    const serial = `FY${STAMP}`;

    await fillSignUp(page, {
        first: 'Fresh', last: 'Yearone', serial,
        email: `fresh${STAMP}@promotrack.local`, username: `fresh${STAMP}`,
        rankCategory: 'rotc', rank: 'MS1', attainment: 'undergraduate', yearLevel: '1st Year 1st Sem', isStudent: true,
        enlistedMonthsAgo: 3,
    });
    await submit(page, 'button.reg-submit');
    await page.goto(`${BASE}/my/rotc`, { waitUntil: 'networkidle0' });
    await settle(page);
    const level = await page.evaluate(() => [...document.querySelectorAll('span')].find((s) => s.textContent.trim() === 'Current Level')?.nextElementSibling?.innerText.trim());
    await page.screenshot({ path: `${OUT}/${label}-rotc.png`, fullPage: true });
    push('ROTC page level', 'E-1 · 1st Year 1st Sem', level);
    await ctx.close();

    await adminPage.goto(`${BASE}/admin/enrollment/MS1?q=${serial}`, { waitUntil: 'networkidle0' });
    await settle(adminPage);
    const found = await adminPage.evaluate((s) => [...document.querySelectorAll('tbody tr')].some((tr) => tr.innerText.includes(s)), serial);
    await adminPage.screenshot({ path: `${OUT}/${label}-ms1.png`, fullPage: true });
    push('listed in the MS1 folder', 'yes', found ? 'yes' : 'no');
}

// Picking Master Sergeant pops the client's RCDG-level notice.
async function masterSergeantNotice(browser, label) {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto(`${BASE}/register`, { waitUntil: 'networkidle0' });
    await page.select('[name="rank_category"]', 'enlisted');
    await pick(page, 'current_rank_id', 'Master Sergeant');
    await page.waitForSelector('#rcdgModal.show', { timeout: 5000 }).catch(() => null);
    const shown = await page.$eval('#rcdgModal', (e) => e.classList.contains('show'));
    const inline = await page.$eval('#rcdgNotice', (e) => !e.classList.contains('d-none'));
    await page.screenshot({ path: `${OUT}/${label}.png` });
    results.push({ path: 'Sign up: Master Sergeant notice', label, expect: 'shown', badge: shown && inline ? 'shown' : `modal ${shown}, inline ${inline}` });
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
    await signUp(browser, '6-signup-eligible', { rank: 'Private First Class', monthsInGrade: 36, expect: 'Eligible' });
    await studentPortal(browser, '7-student-portal');
    await masterSergeantNotice(browser, '8-msg-notice');
    await firstYearStudent(browser, page, '9-first-year');
    }
} finally {
    await browser.close();
    console.log(JSON.stringify(results, null, 2));
    const expected = process.env.REASSESS ? process.env.REASSESS.split(',').length : (process.argv[4] ? 6 : 5) + (process.env.REASSESS ? 0 : 14);
    const ok = results.length === expected
        && results.every((r) => r.badge === r.expect && (r.listBadge === undefined || r.listBadge === r.expect));
    console.log(ok ? `ALL ${results.length} UI CASES MATCH` : 'MISMATCH — see above');
}
