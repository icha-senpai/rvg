import { expect, test, type Page } from '@playwright/test';
import { createHash } from 'node:crypto';

const BASE = 'http://127.0.0.1:5273';
const ISSUER = 'https://citizenid.dev/';
const CLIENT_ID = '6192d6ef-f599-4ec3-9d99-7429ef934a86';
const REDIRECT_URI = 'https://horizon.ichaa.dev/auth/callback';
const SCOPE = 'openid profile roles discord.profile rsi.profile rsi.orgs.primary rsi.orgs.public';
const OIDC_USER_KEY = `oidc.user:${ISSUER}:${CLIENT_ID}`;

function b64url(input: string | Buffer): string {
	return Buffer.from(input).toString('base64url');
}

function makeJwt(payload: Record<string, unknown>): string {
	return `${b64url(JSON.stringify({ alg: 'RS256', typ: 'JWT' }))}.${b64url(JSON.stringify(payload))}.unsigned`;
}

function s256(verifier: string): string {
	return createHash('sha256').update(verifier).digest('base64url');
}

function validIdToken(nonce: string, overrides: Record<string, unknown> = {}): string {
	const now = Math.floor(Date.now() / 1000);
	return makeJwt({
		iss: ISSUER,
		sub: 'cid-test-subject',
		aud: CLIENT_ID,
		exp: now + 3600,
		iat: now,
		nonce,
		name: 'Proof Pilot',
		preferred_username: 'proofpilot',
		'urn:user:rsi:username': 'PROOFPILOT',
		'urn:user:rsi:citizenId': '4242',
		'urn:user:discord:accountId': '1122334455',
		roles: ['CitizenId/Status/Verified'],
		...overrides
	});
}

interface Stubs {
	authorizeUrl: () => URL | null;
	tokenRequests: () => string[];
	tokenPreflights: () => number;
	citizenDirectRequests: () => number;
}

async function stubCitizenId(
	page: Page,
	options: {
		deny?: boolean;
		idToken?: (nonce: string) => string;
		tokenReply?: { status: number; body: string };
		slowPreflight?: boolean;
	} = {}
): Promise<Stubs> {
	let authorizeUrl: URL | null = null;
	const tokenRequests: string[] = [];
	let tokenPreflights = 0;
	let citizenDirectRequests = 0;

	page.on('request', (request) => {
		const url = new URL(request.url());
		if (
			url.hostname === 'citizenid.dev' &&
			(url.pathname === '/.well-known/openid-configuration' || url.pathname === '/connect/token')
		) {
			citizenDirectRequests += 1;
		}
	});

	await page.route('**/citizenid.dev/connect/authorize**', async (route) => {
		authorizeUrl = new URL(route.request().url());
		const state = authorizeUrl.searchParams.get('state') ?? '';
		const location = options.deny
			? `${BASE}/auth/callback?error=access_denied&state=${state}`
			: `${BASE}/auth/callback?code=mock-code-1&state=${state}`;
		await route.fulfill({ status: 302, headers: { location } });
	});

	await page.route('**/horizon.ichaa.dev/auth/token', async (route) => {
		tokenRequests.push(route.request().postData() ?? '');
		const nonce = authorizeUrl?.searchParams.get('nonce') ?? '';
		const idToken = options.idToken ? options.idToken(nonce) : validIdToken(nonce);
		const reply = options.tokenReply ?? {
			status: 200,
			body: JSON.stringify({
				access_token: 'mock-access-token',
				token_type: 'Bearer',
				expires_in: 3600,
				id_token: idToken
			})
		};
		await route.fulfill({
			status: reply.status,
			contentType: 'application/json',
			headers: { 'access-control-allow-origin': '*' },
			body: reply.body
		});
	});

	await page.route('**/127.0.0.1:3000/v1/identity/websocket-token', async (route) => {
		tokenPreflights += 1;
		if (options.slowPreflight) {
			await new Promise((resolve) => setTimeout(resolve, 1000));
		}
		await route.continue();
	});

	return {
		authorizeUrl: () => authorizeUrl,
		tokenRequests: () => tokenRequests,
		tokenPreflights: () => tokenPreflights,
		citizenDirectRequests: () => citizenDirectRequests
	};
}

async function storedUser(page: Page, idToken: string): Promise<void> {
	const payload = JSON.parse(Buffer.from(idToken.split('.')[1], 'base64url').toString());
	await page.addInitScript(
		([key, user]) => {
			window.sessionStorage.setItem(key, JSON.stringify(user));
		},
		[
			OIDC_USER_KEY,
			{
				id_token: idToken,
				session_state: null,
				access_token: 'mock-access-token',
				refresh_token: null,
				token_type: 'Bearer',
				scope: SCOPE,
				profile: payload,
				expires_at: payload.exp
			}
		]
	);
}

const signInButton = (page: Page) => page.getByRole('button', { name: 'Sign in with Citizen iD' });
const retryButton = (page: Page) => page.getByRole('button', { name: 'Retry connection' });

test('authorize request has the exact staging shape and PKCE binds the exchange', async ({
	page
}) => {
	const stubs = await stubCitizenId(page);
	await page.goto(`${BASE}/`);
	await expect(signInButton(page)).toBeVisible({ timeout: 20000 });
	await signInButton(page).click();
	await expect.poll(() => stubs.authorizeUrl() !== null, { timeout: 20000 }).toBe(true);

	const authorize = stubs.authorizeUrl()!;
	const params = authorize.searchParams;
	expect(authorize.origin + authorize.pathname).toBe('https://citizenid.dev/connect/authorize');
	expect(params.get('client_id')).toBe(CLIENT_ID);
	expect(params.get('redirect_uri')).toBe(REDIRECT_URI);
	expect(params.get('response_type')).toBe('code');
	expect(params.get('scope')).toBe(SCOPE);
	expect(params.get('code_challenge_method')).toBe('S256');
	const state = params.get('state');
	const nonce = params.get('nonce');
	expect(state).toBeTruthy();
	expect(nonce).toBeTruthy();
	expect(nonce).not.toBe(state);
	expect(params.get('code_challenge')).toBeTruthy();
	expect(params.get('client_secret')).toBeNull();

	await expect.poll(() => stubs.tokenRequests().length, { timeout: 20000 }).toBe(1);
	const tokenBody = new URLSearchParams(stubs.tokenRequests()[0]);
	expect(tokenBody.get('grant_type')).toBe('authorization_code');
	expect(tokenBody.get('code')).toBe('mock-code-1');
	expect(tokenBody.get('redirect_uri')).toBe(REDIRECT_URI);
	expect(tokenBody.get('client_secret')).toBeNull();
	const verifier = tokenBody.get('code_verifier');
	expect(verifier).toBeTruthy();
	expect(s256(verifier!)).toBe(params.get('code_challenge'));

	await expect.poll(() => stubs.tokenPreflights(), { timeout: 20000 }).toBeGreaterThan(0);
	await expect(retryButton(page)).toBeVisible({ timeout: 20000 });
	await expect(page.getByText('Horizon ID')).toHaveCount(0);
	expect(page.url()).not.toContain('code=');
	expect(page.url()).not.toContain('state=');
	const bodyText = await page.locator('body').innerText();
	expect(bodyText).not.toContain('mock-code-1');
	expect(bodyText).not.toContain('id_token');
	expect(stubs.citizenDirectRequests()).toBe(0);
});

test('forged or missing state never reaches the token endpoint or backend', async ({ page }) => {
	const stubs = await stubCitizenId(page);
	for (const url of [
		`${BASE}/auth/callback?code=forged&state=forged-state`,
		`${BASE}/auth/callback?code=forged`,
		`${BASE}/auth/callback`
	]) {
		await page.goto(url);
		await expect(page.getByRole('alert')).toBeVisible({ timeout: 20000 });
		await expect(page.getByRole('link', { name: 'Back to sign in' })).toBeVisible();
		expect(page.url()).not.toContain('code=');
	}
	expect(stubs.tokenRequests()).toHaveLength(0);
	expect(stubs.tokenPreflights()).toBe(0);
});

test('provider denial shows a safe error and touches nothing else', async ({ page }) => {
	const stubs = await stubCitizenId(page, { deny: true });
	await page.goto(`${BASE}/`);
	await signInButton(page).click();
	await expect(page.getByRole('alert')).toContainText('cancelled or denied', { timeout: 20000 });
	await expect(page.getByRole('link', { name: 'Back to sign in' })).toBeVisible();
	expect(stubs.tokenRequests()).toHaveLength(0);
	expect(stubs.tokenPreflights()).toBe(0);
});

test('bridge OAuth errors surface safe classification', async ({ page }) => {
	const stubs = await stubCitizenId(page, {
		tokenReply: {
			status: 400,
			body: JSON.stringify({ error: 'invalid_grant', error_description: 'SECRET' })
		}
	});
	await page.goto(`${BASE}/`);
	await signInButton(page).click();
	await expect.poll(() => stubs.tokenRequests().length, { timeout: 20000 }).toBe(1);
	await expect
		.poll(async () => {
			const text = await page.locator('body').innerText();
			return text.includes('rejected the authorization code');
		})
		.toBe(true);
	const bodyText = await page.locator('body').innerText();
	expect(bodyText).not.toContain('SECRET');
	expect(stubs.tokenPreflights()).toBe(0);
});

test('callback exchange produces no history API conflict warning', async ({ page }) => {
	const warnings: string[] = [];
	page.on('console', (message) => {
		if (['warning', 'error'].includes(message.type())) warnings.push(message.text());
	});
	await stubCitizenId(page);
	await page.goto(`${BASE}/`);
	await signInButton(page).click();
	await expect(page.getByRole('alert')).toBeVisible({ timeout: 20000 });
	expect(warnings.filter((w) => w.includes('replaceState') || w.includes('pushState'))).toEqual([]);
});

test('mismatched nonce in the id_token is rejected before any backend call', async ({ page }) => {
	const stubs = await stubCitizenId(page, {
		idToken: () => validIdToken('forged-nonce')
	});
	await page.goto(`${BASE}/`);
	await signInButton(page).click();
	await expect(page.getByRole('alert')).toBeVisible({ timeout: 20000 });
	await expect.poll(() => stubs.tokenRequests().length, { timeout: 20000 }).toBe(1);
	expect(stubs.tokenPreflights()).toBe(0);
});

test('wrong issuer, audience, and expired id_tokens are rejected before any backend call', async ({
	page
}) => {
	const now = Math.floor(Date.now() / 1000);
	for (const overrides of [
		{ iss: 'https://citizenid.dev' },
		{ iss: 'https://attacker.example/' },
		{ aud: 'some-other-client' },
		{ aud: ['some-other-client'] },
		{ exp: now - 10 },
		{ exp: now + 3600.5 },
		{ exp: Number.MAX_SAFE_INTEGER + 1000 }
	]) {
		const stubs = await stubCitizenId(page, {
			idToken: (nonce) => validIdToken(nonce, overrides)
		});
		await page.goto(`${BASE}/`);
		await signInButton(page).click();
		await expect(page.getByRole('alert')).toContainText('could not use', { timeout: 20000 });
		await expect.poll(() => stubs.tokenRequests().length, { timeout: 20000 }).toBe(1);
		expect(stubs.tokenPreflights()).toBe(0);
		await page.evaluate(() => window.sessionStorage.clear());
	}
});

test('a consumed callback cannot be replayed for a second exchange', async ({ page }) => {
	const stubs = await stubCitizenId(page);
	await page.goto(`${BASE}/`);
	await signInButton(page).click();
	await expect.poll(() => stubs.tokenRequests().length, { timeout: 20000 }).toBe(1);
	await expect(page).toHaveURL(`${BASE}/`, { timeout: 20000 });

	await page.goto(`${BASE}/auth/callback?code=mock-code-1&state=forged-replay`);
	await expect(page.getByRole('alert')).toBeVisible({ timeout: 20000 });
	expect(stubs.tokenRequests()).toHaveLength(1);
});

test('expired stored session is cleared on load and never connects', async ({ page }) => {
	const now = Math.floor(Date.now() / 1000);
	await storedUser(page, validIdToken('old-nonce', { exp: now - 60 }));
	const stubs = await stubCitizenId(page);
	await page.goto(`${BASE}/`);
	await expect(page.getByRole('status')).toContainText('expired', { timeout: 20000 });
	await expect(signInButton(page)).toBeVisible();
	expect(stubs.tokenPreflights()).toBe(0);
	const stored = await page.evaluate((key) => window.sessionStorage.getItem(key), OIDC_USER_KEY);
	expect(stored).toBeNull();
});

test('stored session reconnects to the real backend and fails safe without ready', async ({
	page
}) => {
	await storedUser(page, validIdToken('resume-nonce'));
	const stubs = await stubCitizenId(page);
	await page.goto(`${BASE}/`);
	await expect.poll(() => stubs.tokenPreflights(), { timeout: 20000 }).toBeGreaterThan(0);
	await expect(retryButton(page)).toBeVisible({ timeout: 20000 });
	await expect(page.getByText('Horizon ID')).toHaveCount(0);

	const before = stubs.tokenPreflights();
	await retryButton(page).click();
	await expect.poll(() => stubs.tokenPreflights(), { timeout: 20000 }).toBeGreaterThan(before);
	await expect(retryButton(page)).toBeVisible({ timeout: 20000 });
});

async function authModuleSpecifier(page: Page): Promise<string> {
	return page.evaluate(async () => {
		const pageSource = await (await fetch('/src/routes/+page.svelte')).text();
		const specifier = pageSource.match(/from\s+["']([^"']*lib\/auth\.ts[^"']*)["']/)?.[1];
		if (!specifier) throw new Error('could not resolve the served auth module URL');
		return specifier;
	});
}

test('local sign out clears the stored session', async ({ page }) => {
	await storedUser(page, validIdToken('signout-nonce'));
	await stubCitizenId(page);
	await page.goto(`${BASE}/`);
	await expect(retryButton(page)).toBeVisible({ timeout: 25000 });
	const specifier = await authModuleSpecifier(page);
	await page.evaluate(async (spec) => (await import(spec)).signOut(), specifier);
	await expect(signInButton(page)).toBeVisible({ timeout: 20000 });
	const stored = await page.evaluate((key) => window.sessionStorage.getItem(key), OIDC_USER_KEY);
	expect(stored).toBeNull();
});

test('sign out during a pending backend connect cannot restore a stale session', async ({
	page
}) => {
	await storedUser(page, validIdToken('stale-nonce'));
	const stubs = await stubCitizenId(page, { slowPreflight: true });
	await page.goto(`${BASE}/`);
	await expect.poll(() => stubs.tokenPreflights(), { timeout: 20000 }).toBeGreaterThan(0);
	const specifier = await authModuleSpecifier(page);
	await page.evaluate(async (spec) => (await import(spec)).signOut(), specifier);
	await expect(signInButton(page)).toBeVisible({ timeout: 20000 });
	await page.waitForTimeout(1500);
	await expect(signInButton(page)).toBeVisible();
	await expect(retryButton(page)).toHaveCount(0);
	await expect(page.getByText('Horizon ID')).toHaveCount(0);
	const stored = await page.evaluate((key) => window.sessionStorage.getItem(key), OIDC_USER_KEY);
	expect(stored).toBeNull();
});

async function setReadyAccount(page: Page, orgFields: Record<string, unknown>): Promise<void> {
	const micros = BigInt(Date.UTC(2026, 9, 13, 18, 0, 0)) * 1000n;
	const account = {
		id: 99,
		status: { tag: 'Pending' },
		displayName: 'Org Pilot',
		preferredUsername: 'orgpilot',
		rsiHandle: 'ORGPILOT',
		rsiCitizenId: '4242',
		rsiSpectrumId: '5555',
		discordAccountId: '112233',
		rsiVerified: true,
		...orgFields
	};
	const specifier = await authModuleSpecifier(page);
	await page.evaluate(
		async ([accountJson, microsText, authSpecifier]) => {
			let sdk: Record<string, unknown> | null = null;
			for (const spec of ['/node_modules/.vite/deps/spacetimedb.js', '/@id/spacetimedb']) {
				sdk = await import(spec).catch(() => null);
				if (sdk) break;
			}
			if (!sdk) throw new Error('could not load the SpaceTimeDB module');
			const Timestamp = sdk.Timestamp as new (micros: bigint) => {
				toISOString(): string;
				toDate(): Date;
			};
			const { authState } = await import(authSpecifier);
			authState.set({
				status: 'ready',
				error: null,
				notice: null,
				canRetry: true,
				account: {
					...JSON.parse(accountJson),
					lastSeenAt: new Timestamp(BigInt(microsText))
				}
			});
		},
		[JSON.stringify(account), micros.toString(), specifier]
	);
}

test('account card renders org snapshots and a localized last-seen time', async ({ page }) => {
	await page.goto(`${BASE}/`);
	await expect(signInButton(page)).toBeVisible();
	await setReadyAccount(page, { rsiPrimaryOrg: 'SRN', rsiPublicOrgs: ['SRN', 'ALLY'] });

	await expect(page.getByText('Horizon ID')).toBeVisible({ timeout: 20000 });
	await expect(page.getByText('99')).toBeVisible();
	await expect(page.getByText('SRN', { exact: true })).toBeVisible();
	await expect(page.getByText('SRN, ALLY')).toBeVisible();
	const time = page.locator('time');
	await expect(time).toHaveAttribute('datetime', '2026-10-13T18:00:00.000000Z');
	const expectedText = await page.evaluate(
		(ms) => new Date(ms).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }),
		Date.UTC(2026, 9, 13, 18, 0, 0)
	);
	await expect(time).toHaveText(expectedText);
});

test('account card shows explicit fallbacks when orgs are not shared', async ({ page }) => {
	await page.goto(`${BASE}/`);
	await expect(signInButton(page)).toBeVisible();
	await setReadyAccount(page, { rsiPrimaryOrg: undefined, rsiPublicOrgs: [] });

	await expect(page.getByText('Horizon ID')).toBeVisible({ timeout: 20000 });
	await expect(page.getByText('Not shared')).toBeVisible();
	await expect(page.getByText('None shared')).toBeVisible();
	const bodyText = await page.locator('body').innerText();
	expect(bodyText).not.toContain('[object Object]');
});
