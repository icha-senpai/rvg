import { browser } from '$app/env';
import { PUBLIC_SPACETIME_DATABASE, PUBLIC_SPACETIME_URI } from '$app/env/public';
import { get, writable } from 'svelte/store';
import { ErrorResponse, Log, User, UserManager, WebStorageStateStore } from 'oidc-client-ts';
import { DbConnection, tables } from './spacetime';
import type { CurrentUser } from './spacetime';

const CITIZEN_ID_ISSUER = 'https://citizenid.dev/';
const CITIZEN_ID_CLIENT_ID = '6192d6ef-f599-4ec3-9d99-7429ef934a86';
const CITIZEN_ID_REDIRECT_URI = 'https://horizon.ichaa.dev/auth/callback';
const CITIZEN_ID_SCOPE =
	'openid profile roles discord.profile rsi.profile rsi.orgs.primary rsi.orgs.public';
const IDENTITY_SYNC_TIMEOUT_MS = 8000;

export type AuthStatus = 'signed_out' | 'signing_in' | 'connecting' | 'ready' | 'disconnected';

export interface AuthState {
	status: AuthStatus;
	error: string | null;
	notice: string | null;
	canRetry: boolean;
	account: CurrentUser | null;
}

const initialState: AuthState = {
	status: 'signed_out',
	error: null,
	notice: null,
	canRetry: false,
	account: null
};

export const authState = writable<AuthState>({ ...initialState });

export class SafeAuthError extends Error {}

let userManager: UserManager | null = null;
let connection: DbConnection | null = null;
let expiryTimer: ReturnType<typeof setTimeout> | null = null;
let readyTimer: ReturnType<typeof setTimeout> | null = null;
let generation = 0;
let signinInFlight: Promise<void> | null = null;
let initialized = false;

function spacetimeUri(): string {
	return PUBLIC_SPACETIME_URI ?? 'ws://127.0.0.1:3000';
}

function spacetimeDatabase(): string {
	return PUBLIC_SPACETIME_DATABASE ?? 'horizon-2-owned';
}

function manager(): UserManager {
	if (!browser) {
		throw new SafeAuthError('Sign-in is only available in the browser.');
	}
	if (!userManager) {
		Log.setLevel(Log.NONE);
		userManager = new UserManager({
			authority: CITIZEN_ID_ISSUER,
			metadata: {
				issuer: CITIZEN_ID_ISSUER,
				authorization_endpoint: 'https://citizenid.dev/connect/authorize',
				token_endpoint: new URL('/auth/token', CITIZEN_ID_REDIRECT_URI).href,
				jwks_uri: 'https://citizenid.dev/.well-known/jwks'
			},
			client_id: CITIZEN_ID_CLIENT_ID,
			redirect_uri: CITIZEN_ID_REDIRECT_URI,
			response_type: 'code',
			response_mode: 'query',
			scope: CITIZEN_ID_SCOPE,
			automaticSilentRenew: false,
			monitorSession: false,
			loadUserInfo: false,
			disablePKCE: false,
			filterProtocolClaims: false,
			userStore: new WebStorageStateStore({ store: window.sessionStorage }),
			stateStore: new WebStorageStateStore({ store: window.sessionStorage })
		});
	}
	return userManager;
}

function patchState(patch: Partial<AuthState>): void {
	authState.update((state) => ({ ...state, ...patch }));
}

function profileClaims(user: User): Record<string, unknown> {
	return user.profile as unknown as Record<string, unknown>;
}

function validateProfile(user: User): boolean {
	const claims = profileClaims(user);
	if (claims.iss !== CITIZEN_ID_ISSUER) return false;
	if (typeof claims.sub !== 'string' || claims.sub.trim() === '') return false;
	const aud = claims.aud;
	const audiences =
		typeof aud === 'string'
			? [aud]
			: Array.isArray(aud) && aud.every((entry) => typeof entry === 'string')
				? (aud as string[])
				: [];
	if (audiences.length === 0 || !audiences.includes(CITIZEN_ID_CLIENT_ID)) return false;
	if (claims.azp != null && claims.azp !== CITIZEN_ID_CLIENT_ID) return false;
	if (audiences.length > 1 && claims.azp !== CITIZEN_ID_CLIENT_ID) return false;
	const now = Date.now();
	const exp = claims.exp;
	const nbf = claims.nbf;
	if (typeof exp !== 'number' || !Number.isSafeInteger(exp) || exp * 1000 <= now) return false;
	if (nbf != null && (typeof nbf !== 'number' || !Number.isSafeInteger(nbf) || nbf * 1000 > now))
		return false;
	return true;
}

function sessionUsable(user: User | null): user is User {
	return (
		!!user && typeof user.id_token === 'string' && user.id_token.length > 0 && validateProfile(user)
	);
}

function clearExpiry(): void {
	if (expiryTimer !== null) {
		clearTimeout(expiryTimer);
		expiryTimer = null;
	}
}

function clearReadyTimer(): void {
	if (readyTimer !== null) {
		clearTimeout(readyTimer);
		readyTimer = null;
	}
}

const MAX_TIMEOUT_MS = 2147483647;

function scheduleExpiry(user: User): void {
	clearExpiry();
	const exp = profileClaims(user).exp;
	if (typeof exp !== 'number' || !Number.isSafeInteger(exp)) return;
	const delay = Math.min(Math.max(exp * 1000 - Date.now(), 0), MAX_TIMEOUT_MS);
	expiryTimer = setTimeout(() => {
		expiryTimer = null;
		if (exp * 1000 <= Date.now()) {
			void expireSession();
		} else {
			scheduleExpiry(user);
		}
	}, delay);
}

function dropConnection(): void {
	if (connection) {
		connection.disconnect();
		connection = null;
	}
}

async function expireSession(): Promise<void> {
	const gen = (generation += 1);
	clearExpiry();
	clearReadyTimer();
	dropConnection();
	await manager().removeUser();
	if (gen !== generation) return;
	patchState({
		...initialState,
		notice: 'Your Citizen iD sign-in expired. Please sign in again.'
	});
}

function backendDown(message: string): void {
	clearReadyTimer();
	patchState({
		status: 'disconnected',
		error: message,
		notice: null,
		canRetry: true,
		account: null
	});
}

function connect(user: User): void {
	const gen = (generation += 1);
	const idToken = user.id_token;
	if (typeof idToken !== 'string' || idToken.length === 0) {
		patchState({
			...initialState,
			error: 'Citizen iD did not return a usable identity. Please sign in again.'
		});
		return;
	}
	scheduleExpiry(user);
	clearReadyTimer();
	dropConnection();
	patchState({ status: 'connecting', error: null, notice: null, canRetry: true, account: null });
	const builder = DbConnection.builder()
		.withUri(spacetimeUri())
		.withDatabaseName(spacetimeDatabase())
		.withToken(idToken)
		.onConnect((conn) => {
			if (gen !== generation) return;
			watchCurrentUser(conn, gen);
		})
		.onConnectError(() => {
			if (gen !== generation) return;
			backendDown('Horizon could not authenticate this sign-in or the database is unreachable.');
		})
		.onDisconnect(() => {
			if (gen !== generation) return;
			backendDown('The connection to Horizon was lost.');
		});
	connection = builder.build();
}

function watchCurrentUser(conn: DbConnection, gen: number): void {
	conn.db.currentUser.onInsert((_ctx, row) => {
		if (gen !== generation) return;
		markReady(row);
	});
	conn.db.currentUser.onUpdate((_ctx, _oldRow, row) => {
		if (gen !== generation) return;
		markReady(row);
	});
	conn.db.currentUser.onDelete(() => {
		if (gen !== generation) return;
		const remaining = conn.db.currentUser.iter().next().value as CurrentUser | undefined;
		if (remaining) {
			markReady(remaining);
		} else {
			patchState({ status: 'connecting', error: null, account: null, canRetry: true });
		}
	});
	conn
		.subscriptionBuilder()
		.onApplied((ctx) => {
			if (gen !== generation) return;
			const row = ctx.db.currentUser.iter().next().value as CurrentUser | undefined;
			if (row) {
				markReady(row);
				return;
			}
			conn.reducers.syncCitizenIdentity({}).catch(() => {
				if (gen !== generation) return;
				backendDown('Horizon could not confirm your identity yet. Try again shortly.');
			});
			clearReadyTimer();
			readyTimer = setTimeout(() => {
				if (gen !== generation) return;
				if (get(authState).status === 'connecting') {
					backendDown('Horizon is taking too long to confirm your identity. Try again shortly.');
				}
			}, IDENTITY_SYNC_TIMEOUT_MS);
		})
		.onError(() => {
			if (gen !== generation) return;
			backendDown('Horizon could not load your account view. Try again shortly.');
		})
		.subscribe(tables.currentUser.build());
}

function markReady(row: CurrentUser): void {
	clearReadyTimer();
	patchState({ status: 'ready', error: null, notice: null, canRetry: true, account: row });
}

export async function initializeAuth(): Promise<void> {
	if (!browser || initialized) return;
	initialized = true;
	const recheck = () => {
		const gen = generation;
		void manager()
			.getUser()
			.then((user) => {
				if (gen !== generation) return;
				if (user && !sessionUsable(user)) {
					void expireSession();
				}
			});
	};
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'visible') recheck();
	});
	window.addEventListener('focus', recheck);
	const gen = generation;
	try {
		const user = await manager().getUser();
		if (gen !== generation) return;
		if (sessionUsable(user)) {
			connect(user);
		} else if (user) {
			await manager().removeUser();
			if (gen !== generation) return;
			patchState({
				...initialState,
				notice: 'Your previous sign-in expired. Please sign in again.'
			});
		}
	} catch {
		if (gen !== generation) return;
		patchState({
			...initialState,
			error: 'Sign-in state could not be restored. Please sign in again.'
		});
	}
}

export async function signIn(): Promise<void> {
	if (!browser) return;
	const gen = (generation += 1);
	clearExpiry();
	clearReadyTimer();
	dropConnection();
	patchState({ status: 'signing_in', error: null, notice: null, canRetry: false, account: null });
	try {
		await manager().signinRedirect({ nonce: crypto.randomUUID() });
	} catch {
		if (gen !== generation) return;
		patchState({
			...initialState,
			error: 'Citizen iD sign-in is unavailable right now. Please try again later.'
		});
	}
}

export function completeSignin(callbackUrl: string): Promise<void> {
	if (signinInFlight) return signinInFlight;
	signinInFlight = runSigninCallback(callbackUrl).finally(() => {
		signinInFlight = null;
	});
	return signinInFlight;
}

function exchangeErrorMessage(caught: unknown, providerDenied: boolean): string {
	if (providerDenied) return 'Sign-in was cancelled or denied by Citizen iD.';
	const code = caught instanceof ErrorResponse ? caught.error : undefined;
	switch (code) {
		case 'invalid_grant':
			return 'Citizen iD rejected the authorization code. Start a fresh sign-in.';
		case 'invalid_client':
		case 'unauthorized_client':
			return 'Citizen iD rejected Horizon’s client configuration.';
		case 'invalid_scope':
			return 'Citizen iD rejected the requested identity scopes.';
		case 'invalid_request':
		case 'unsupported_grant_type':
			return 'The sign-in exchange request was rejected.';
		case 'server_error':
		case 'temporarily_unavailable':
			return 'The sign-in exchange service is unavailable. Please try again.';
		default:
			return 'Citizen iD sign-in could not be completed. Please try again.';
	}
}

async function runSigninCallback(callbackUrl: string): Promise<void> {
	if (!browser) throw new SafeAuthError('Sign-in is only available in the browser.');
	const gen = (generation += 1);
	const providerDenied = new URL(callbackUrl).searchParams.has('error');
	let user: User;
	try {
		user = await manager().signinRedirectCallback(callbackUrl);
	} catch (caught) {
		const message = exchangeErrorMessage(caught, providerDenied);
		patchState({ ...initialState, error: message });
		throw new SafeAuthError(message);
	}
	if (gen !== generation) {
		const stored = await manager().getUser();
		if (stored && stored.id_token === user.id_token) {
			await manager().removeUser();
		}
		return;
	}
	if (!sessionUsable(user)) {
		await manager().removeUser();
		patchState({
			...initialState,
			error: 'Citizen iD returned an identity Horizon could not use. Please try again.'
		});
		throw new SafeAuthError(
			'Citizen iD returned an identity Horizon could not use. Please try again.'
		);
	}
	connect(user);
}

export async function retryConnection(): Promise<void> {
	if (!browser) return;
	const gen = generation;
	const user = await manager().getUser();
	if (gen !== generation) return;
	if (sessionUsable(user)) {
		connect(user);
		return;
	}
	if (user) await manager().removeUser();
	if (gen !== generation) return;
	patchState({ ...initialState, notice: 'Your sign-in expired. Please sign in again.' });
}

export async function signOut(): Promise<void> {
	if (!browser) return;
	const gen = (generation += 1);
	clearExpiry();
	clearReadyTimer();
	dropConnection();
	await manager().removeUser();
	if (gen !== generation) return;
	patchState({ ...initialState });
}
