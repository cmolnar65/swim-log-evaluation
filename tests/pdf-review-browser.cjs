/* Run against actual synthetic WordPress admin HTML, never confidential PDFs. */
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
(async () => {
  const log = fs.readFileSync(process.argv[2], 'utf8');
  const match = log.match(/SWIMLOG_PDF_REVIEW_HTML_BASE64=([A-Za-z0-9+/=]+)/);
  if (!match) throw Error('Missing rendered WordPress review HTML');
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage({viewport:{width:1280,height:900}});
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setContent(Buffer.from(match[1], 'base64').toString('utf8'));
    await page.addStyleTag({path:path.resolve('assets/css/structured-workout-editor.css')});
    await page.addScriptTag({path:path.resolve('assets/js/structured-workout-editor.js')});
    const form = page.locator('form[data-swimlog-pdf-import]');
    await form.locator('[name="swimlog_pdf_plan_name"]').fill('Synthetic browser review');
    const candidates = form.locator('.swimlog-pdf-candidate');
    if (await candidates.count() !== 3) throw Error('Candidate controls are missing');
    for (let i=0;i<3;i++) {
      const candidate = candidates.nth(i);
      await candidate.locator('summary').click();
      await candidate.locator('[data-swimlog-pdf-editor] .swimlog-structure-panel').first().waitFor();
    }
    const first = candidates.nth(0);
    await first.locator('[name="swimlog_pdf_selected[]"]').check();
    if (await form.evaluate(el=>el.checkValidity())) throw Error('Selection bypassed required review');
    await first.getByLabel('Distance', {exact:true}).fill('125');
    await first.locator('[name$="[total]"]').fill('500');
    await first.locator('[name$="[unit]"]').selectOption('yd');
    await first.locator('[name$="[reviewed]"]').check();
    const second = candidates.nth(1);
    await second.locator('[name="swimlog_pdf_selected[]"]').check();
    await second.getByLabel('Distance', {exact:true}).fill('200');
    await second.locator('[name$="[total]"]').fill('200');
    await second.locator('[name$="[unit]"]').selectOption('m');
    await second.locator('[name$="[reviewed]"]').check();
    if (!await form.evaluate(el=>el.checkValidity())) throw Error('Unselected candidate blocks import');
    const values = await form.evaluate(el=>{
      el.addEventListener('submit', event=>event.preventDefault());
      el.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}));
      return Array.from(new FormData(el).entries());
    });
    const data = Object.fromEntries(values);
    if (JSON.parse(data['swimlog_pdf_reviews[1][document]']).sections[0].children[0].children[0].distance !== 125) throw Error('First editor lost correction');
    if (JSON.parse(data['swimlog_pdf_reviews[2][document]']).sections[0].children[0].distance !== 200) throw Error('Editors shared state');
    if (values.some(([key])=>key.startsWith('swimlog_pdf_reviews[3]'))) throw Error('Unselected candidate submitted review inputs');
    if (values.filter(([key])=>key==='swimlog_pdf_selected[]').length !== 2) throw Error('Selection not submitted');
    if (errors.length) throw Error(errors.join('\n'));
    await page.screenshot({path:process.env.RUNNER_TEMP ? path.join(process.env.RUNNER_TEMP,'pdf-review-ui.png') : '/tmp/pdf-review-ui.png',fullPage:true});
    console.log('PDF review browser: selection, required approval, independent correction editors, totals, lazy rendering and selected-only form submission passed.');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1);});
