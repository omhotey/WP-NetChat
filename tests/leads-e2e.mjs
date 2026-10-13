import assert from 'node:assert/strict';
export async function runLeadsFrontendChecks(page) {
    await page.evaluate(async () => { await window.NetChat.ready; });
    assert.equal(await page.evaluate(() => window.NetChat.openForm({form:'unknown'})),false);
    await page.evaluate(() => {
        const button=document.createElement('button');button.id='nc-test-external';button.textContent='Contact';
        button.setAttribute('data-netchat-open','form');button.setAttribute('data-netchat-context','FREE_CONTEXT');document.body.appendChild(button);
    });
    const opener=page.locator('#nc-test-external'),modal=page.locator('#nc-leads-modal');
    await opener.click();await modal.waitFor({state:'visible'});
    assert.equal(await modal.locator('[role="dialog"]').getAttribute('aria-modal'),'true');
    assert.equal(await page.evaluate(() => !!document.activeElement.closest('#nc-leads-modal')),true);
    await page.locator('.nc-leads-submit').focus();await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.classList.contains('nc-leads-close')),true);
    await page.keyboard.press('Shift+Tab');assert.equal(await page.evaluate(() => document.activeElement.classList.contains('nc-leads-submit')),true);
    await page.keyboard.press('Escape');await modal.waitFor({state:'hidden'});
    assert.equal(await opener.evaluate(e => e===document.activeElement),true);
    assert.equal(await opener.evaluate(e => e.closest('[inert]')===null),true);
    await opener.click();await page.locator('#nc-lead-email').fill('leads-ui@example.test');await page.locator('#nc-lead-message').fill('Keep after failed request.');
    const bodies=[];let posts=0;
    await page.route('**/netchat/v1/leads/token',route=>route.fulfill({json:{token:'browser-test-token'}}));
    await page.route('**/netchat/v1/leads',async route=>{
        bodies.push(route.request().postDataJSON());posts++;
        if(posts===1)return route.abort('failed');
        return route.fulfill({status:200,json:{success:true,created:false}});
    });
    try {
        await page.locator('.nc-leads-submit').click();await page.waitForFunction(()=>document.getElementById('nc-leads-status').hasAttribute('data-error'));
        assert.equal(await page.locator('#nc-lead-message').inputValue(),'Keep after failed request.');
        await page.locator('.nc-leads-submit').click();await page.waitForFunction(()=>{const s=document.getElementById('nc-leads-status');return s.textContent&&!s.hasAttribute('data-error')&&!document.getElementById('nc-leads-form').hasAttribute('aria-busy');});
        assert.equal(bodies.length,2);assert.equal(bodies[0].request_key,bodies[1].request_key);assert.equal(bodies[1].context,'FREE_CONTEXT');
        assert.equal(await page.locator('#nc-lead-email').inputValue(),'');
        assert.equal(await page.locator('#nc-leads-form').isVisible(),false);
        assert.equal(await page.locator('#nc-leads-success').isVisible(),true);
        await page.screenshot({path:'tests/results/lead-success.png'});
        await page.locator('.nc-leads-done').click();await modal.waitFor({state:'hidden'});
        await page.evaluate(()=>window.NetChat.close());await modal.waitFor({state:'hidden'});
        await page.setViewportSize({width:390,height:844});await page.emulateMedia({reducedMotion:'reduce'});await opener.click();
        assert.equal(await modal.locator('[role="dialog"]').evaluate(e=>getComputedStyle(e).animationName),'none');
        const bounds=await modal.locator('[role="dialog"]').boundingBox();assert.ok(bounds.width<=390&&bounds.height<=844);await page.keyboard.press('Escape');
        await opener.click();await page.locator('[role="dialog"]').evaluate(e=>e.scrollTop=e.scrollHeight);
        await page.screenshot({path:'tests/results/lead-mobile.png'});
        const close=page.locator('.nc-leads-close');await close.click();await modal.waitFor({state:'hidden'});
    } finally {
        await page.unroute('**/netchat/v1/leads/token');await page.unroute('**/netchat/v1/leads');
        await page.evaluate(()=>{window.NetChat.closeForm();document.getElementById('nc-test-external')?.remove();});
    }
    console.log('PASS lead UI: independent CTA, focus/trap/Escape, failure preservation, stable retry, mobile/reduced motion.');
}
