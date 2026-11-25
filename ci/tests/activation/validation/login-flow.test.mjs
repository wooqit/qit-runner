import assert from 'node:assert/strict';
import http from 'node:http';
import {createRequire} from 'node:module';
import path from 'node:path';
import {after, before, beforeEach, test} from 'node:test';
import {fileURLToPath} from 'node:url';

import qit from '../test-package/qit-helpers/index.js';

const validationDirectory = path.dirname(fileURLToPath(import.meta.url));
const packageRoot = path.resolve(validationDirectory, '../test-package');
const {chromium} = createRequire(path.join(packageRoot, 'package.json'))('@playwright/test');

const USERNAME = 'admin';
const PASSWORD = 'password';

// wp_attempt_focus() as wp-login.php prints it: 200ms after load it focuses and selects the username field.
const CORE_AUTOFOCUS = `function wp_attempt_focus() {setTimeout( function() {try {d = document.getElementById( "user_login" );d.focus(); d.select();} catch( er ) {}}, 200);}
wp_attempt_focus();`;

// The same callback, timed to land where it landed in the failing Composite Products runs (QIT-1225): after
// Playwright focuses the password field and before it types into it. fill() focuses the field in one call and
// types in a second, so a timer queued on that focus runs in the gap, as the 200ms one did on a slow runner.
const AUTOFOCUS_DURING_PASSWORD_FILL = `document.getElementById( "user_pass" ).addEventListener( "focus", function() {
    setTimeout( function() { var d = document.getElementById( "user_login" ); d.focus(); d.select(); }, 0 );
}, { once: true } );`;

let loginScript = CORE_AUTOFOCUS;
let acceptCredentials = true;
// How a rejected login answers: 'form' renders the login form, as core does; 'redirect' sends the browser back to it.
let rejectWith = 'form';
let loggedInCookie = 'wordpress_logged_in_stub';
let submitLabel = 'Log In';
let loginPosts = [];

// The parts of the wp-login.php login form the helper touches, as WordPress core renders them.
function loginPage(error = '') {
    return `<!DOCTYPE html>
<html lang="en-US"><head><meta charset="UTF-8"><title>Log In</title></head>
<body class="login">
<div id="login">
${error ? `<div id="login_error" class="notice notice-error"><p>${error}</p></div>` : ''}
<form name="loginform" id="loginform" action="/wp-login.php" method="post">
    <p>
        <label for="user_login">Username or Email Address</label>
        <input type="text" name="log" id="user_login" class="input ltr" value="" size="20" autocapitalize="off" autocomplete="username" required="required" />
    </p>
    <div class="user-pass-wrap">
        <label for="user_pass">Password</label>
        <div class="wp-pwd">
            <input type="password" name="pwd" id="user_pass" class="input password-input ltr" value="" size="20" autocomplete="current-password" spellcheck="false" required="required" />
            <button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Show password"></button>
        </div>
    </div>
    <p class="forgetmenot"><input name="rememberme" type="checkbox" id="rememberme" value="forever" /> <label for="rememberme">Remember Me</label></p>
    <p class="submit">
        <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="${submitLabel}" />
        <input type="hidden" name="redirect_to" value="/wp-admin/" />
        <input type="hidden" name="testcookie" value="1" />
    </p>
</form>
</div>
${error ? '' : `<script>${loginScript}</script>`}
</body></html>`;
}

function readBody(request) {
    return new Promise((resolve, reject) => {
        let body = '';
        request.on('data', chunk => body += chunk);
        request.on('end', () => resolve(body));
        request.on('error', reject);
    });
}

const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, 'http://localhost');

    if (url.pathname === '/wp-login.php' && request.method === 'POST') {
        const form = new URLSearchParams(await readBody(request));
        loginPosts.push({log: form.get('log'), pwd: form.get('pwd')});

        if (acceptCredentials && form.get('log') === USERNAME && form.get('pwd') === PASSWORD) {
            response.writeHead(302, {
                'Location': '/wp-admin/',
                'Set-Cookie': `${loggedInCookie}=admin; Path=/; HttpOnly`,
            });
            response.end();
            return;
        }

        if (rejectWith === 'redirect') {
            response.writeHead(302, {'Location': '/wp-login.php?login=failed'});
            response.end();
            return;
        }

        response.writeHead(200, {'Content-Type': 'text/html; charset=UTF-8'});
        response.end(loginPage('<strong>Error:</strong> The password you entered for the username <strong>admin</strong> is incorrect.'));
        return;
    }

    if (url.pathname === '/wp-login.php') {
        response.writeHead(200, {'Content-Type': 'text/html; charset=UTF-8'});
        response.end(loginPage());
        return;
    }

    if (url.pathname === '/wp-admin/') {
        const cookies = (request.headers.cookie || '').split(/;\s*/).map(cookie => cookie.split('=')[0]);
        if (!cookies.includes(loggedInCookie)) {
            response.writeHead(302, {'Location': '/wp-login.php?redirect_to=%2Fwp-admin%2F&reauth=1'});
            response.end();
            return;
        }

        response.writeHead(200, {'Content-Type': 'text/html; charset=UTF-8'});
        response.end('<!DOCTYPE html><html><head><title>Dashboard</title></head><body><h1>Dashboard</h1></body></html>');
        return;
    }

    response.writeHead(404);
    response.end();
});

let browser;
let baseURL;

before(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    baseURL = `http://127.0.0.1:${server.address().port}`;
    browser = await chromium.launch();
});

after(async () => {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
});

beforeEach(() => {
    loginScript = CORE_AUTOFOCUS;
    acceptCredentials = true;
    rejectWith = 'form';
    loggedInCookie = 'wordpress_logged_in_stub';
    submitLabel = 'Log In';
    loginPosts = [];
});

async function withPage(callback, cookies = []) {
    const context = await browser.newContext({baseURL});
    await context.addCookies(cookies.map(cookie => ({...cookie, url: baseURL})));
    try {
        return await callback(await context.newPage());
    } finally {
        await context.close();
    }
}

async function seesDashboard(page) {
    await page.goto('/wp-admin/');
    return page.getByRole('heading', {name: 'Dashboard'}).isVisible();
}

// Guards the test below: if the stub stopped reproducing the race, it would pass without proving anything.
test('the stub login page reproduces the autofocus race against a plain fill and click', async () => {
    loginScript = AUTOFOCUS_DURING_PASSWORD_FILL;

    await withPage(async page => {
        await page.goto('/wp-admin/');
        await page.getByLabel('Username or Email Address').fill(USERNAME);
        await page.getByLabel('Password', {exact: true}).fill(PASSWORD);
        await page.getByRole('button', {name: 'Log In'}).click();
        await page.waitForLoadState('networkidle');

        assert.equal(await page.getByLabel('Username or Email Address').inputValue(), PASSWORD);
        assert.equal(await page.getByLabel('Password', {exact: true}).inputValue(), '');
        assert.equal(await seesDashboard(page), false);
    });

    assert.deepEqual(loginPosts, []);
});

test('logs in when the login page refocuses the username during the password fill', async () => {
    loginScript = AUTOFOCUS_DURING_PASSWORD_FILL;

    await withPage(async page => {
        await qit.loginAs(page, USERNAME, PASSWORD);
        assert.equal(await seesDashboard(page), true);
    });

    assert.deepEqual(loginPosts, [{log: USERNAME, pwd: PASSWORD}]);
});

test('logs in on the login page as WordPress core renders it', async () => {
    await withPage(async page => {
        await qit.loginAs(page, USERNAME, PASSWORD);
        assert.equal(await seesDashboard(page), true);
    });

    assert.deepEqual(loginPosts, [{log: USERNAME, pwd: PASSWORD}]);
});

test('fails with the login error when WordPress rejects the credentials', async () => {
    acceptCredentials = false;

    await withPage(async page => {
        await assert.rejects(
            qit.loginAs(page, USERNAME, PASSWORD),
            /Logging in as "admin" failed: Error: The password you entered for the username admin is incorrect\./
        );
    });

    assert.deepEqual(loginPosts, [{log: USERNAME, pwd: PASSWORD}]);
});

test('logs in when the logged-in cookie has a custom name', async () => {
    loggedInCookie = 'qit_custom_logged_in';

    await withPage(async page => {
        await qit.loginAs(page, USERNAME, PASSWORD);
        assert.equal(await seesDashboard(page), true);
    });
});

test('fails a rejected login even when a logged-in cookie from another session is present', async () => {
    acceptCredentials = false;

    await withPage(async page => {
        await assert.rejects(qit.loginAs(page, USERNAME, PASSWORD), /Logging in as "admin" failed: Error: The password/);
    }, [{name: 'wordpress_logged_in_othersite', value: 'someone-else'}]);
});

test('a failed click leaves no login wait to reject unhandled', async () => {
    submitLabel = 'Sign In';
    const unhandled = [];
    const recordUnhandled = reason => unhandled.push(reason);
    process.on('unhandledRejection', recordUnhandled);

    try {
        await withPage(async page => {
            page.setDefaultTimeout(2000);
            await assert.rejects(qit.loginAs(page, USERNAME, PASSWORD), /locator\.click: Timeout/);
        });
        // Closing the context rejects a login wait that is still pending; give that rejection time to surface.
        await new Promise(resolve => setTimeout(resolve, 500));
    } finally {
        process.off('unhandledRejection', recordUnhandled);
    }

    assert.deepEqual(unhandled.map(String), []);
    assert.deepEqual(loginPosts, []);
});

test('fails a rejected login that redirects back to the login form', async () => {
    acceptCredentials = false;
    rejectWith = 'redirect';

    await withPage(async page => {
        await assert.rejects(qit.loginAs(page, USERNAME, PASSWORD), /Logging in as "admin" failed .*wp-login\.php\?login=failed/);
    });

    assert.deepEqual(loginPosts, [{log: USERNAME, pwd: PASSWORD}]);
});

test('saves the admin cookies only after a login that succeeded', async t => {
    const saved = [];
    t.mock.method(qit, 'setEnv', key => saved.push(key));

    await withPage(page => qit._performLoginFlow(page, USERNAME, PASSWORD, true));
    assert.deepEqual(saved, ['admin_cookies']);

    saved.length = 0;
    acceptCredentials = false;
    rejectWith = 'redirect';

    await withPage(async page => {
        await assert.rejects(qit._performLoginFlow(page, USERNAME, PASSWORD, true), /Logging in as "admin" failed/);
    });
    assert.deepEqual(saved, []);
});
