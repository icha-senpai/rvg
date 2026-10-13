<script lang="ts">
	import { onMount } from 'svelte';
	import { authState, initializeAuth, retryConnection, signIn, signOut } from '#lib/auth.ts';

	onMount(() => {
		void initializeAuth();
	});
</script>

<svelte:head>
	<title>Horizon 2</title>
</svelte:head>

<main>
	<h1>Horizon 2</h1>
	<p class="staging-note">Citizen iD staging sign-in proof. Local SpaceTimeDB only.</p>

	<section class="panel" aria-live="polite">
		{#if $authState.notice}
			<p role="status">{$authState.notice}</p>
		{/if}
		{#if $authState.error}
			<p role="alert">{$authState.error}</p>
		{/if}

		{#if $authState.status === 'signed_out'}
			<button type="button" onclick={() => void signIn()}>Sign in with Citizen iD</button>
		{:else if $authState.status === 'signing_in'}
			<p role="status">Redirecting to Citizen iD…</p>
		{:else if $authState.status === 'connecting'}
			<p role="status">Connecting to Horizon…</p>
		{:else if $authState.status === 'disconnected'}
			{#if $authState.canRetry}
				<button type="button" onclick={() => void retryConnection()}>Retry connection</button>
			{/if}
			<button type="button" onclick={() => void signIn()}>Sign in with Citizen iD</button>
		{:else if $authState.status === 'ready' && $authState.account}
			{@const account = $authState.account}
			<dl class="account">
				<dt>Horizon ID</dt>
				<dd>{account.id}</dd>
				<dt>Account status</dt>
				<dd>{account.status.tag === 'Pending' ? 'Pending' : account.status.tag}</dd>
				<dt>Display name</dt>
				<dd>{account.displayName ?? account.preferredUsername ?? 'Not shared'}</dd>
				<dt>RSI handle</dt>
				<dd>{account.rsiHandle ?? 'No RSI profile shared'}</dd>
				<dt>RSI verified</dt>
				<dd>{account.rsiVerified ? 'Verified by Citizen iD' : 'Not verified'}</dd>
				<dt>RSI citizen ID</dt>
				<dd>{account.rsiCitizenId ?? 'Not shared'}</dd>
				<dt>RSI spectrum ID</dt>
				<dd>{account.rsiSpectrumId ?? 'Not shared'}</dd>
				<dt>Discord account</dt>
				<dd>{account.discordAccountId ?? 'No Discord account linked'}</dd>
				<dt>Primary RSI organization</dt>
				<dd>{account.rsiPrimaryOrg ?? 'Not shared'}</dd>
				<dt>Public RSI organizations</dt>
				<dd>{(account.rsiPublicOrgs ?? []).join(', ') || 'None shared'}</dd>
				<dt>Last seen</dt>
				<dd>
					<time datetime={account.lastSeenAt.toISOString()}
						>{account.lastSeenAt
							.toDate()
							.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}</time
					>
				</dd>
			</dl>
			<p class="authority-note">
				Snapshot data from Citizen iD. Your Horizon account is pending — no ranks, admin rights, or
				squadron membership are granted by this sign-in.
			</p>
			<button type="button" onclick={() => void signOut()}>Sign out of Horizon</button>
			<p class="authority-note">Citizen iD may keep you signed in on their site.</p>
		{/if}
	</section>
</main>

<style>
	main {
		max-width: 36rem;
		margin: 3rem auto;
		padding: 0 1rem;
		font-family: system-ui, sans-serif;
		color: #1c1c22;
	}
	.staging-note {
		color: #55555f;
	}
	.panel {
		margin-top: 1.5rem;
		padding: 1.25rem;
		border: 1px solid #d0d0d8;
		border-radius: 0.5rem;
	}
	button {
		padding: 0.5rem 1rem;
		border: 1px solid #26262e;
		border-radius: 0.375rem;
		background: #26262e;
		color: #f5f5f7;
		cursor: pointer;
	}
	button:hover {
		background: #32323c;
	}
	button + button {
		margin-left: 0.75rem;
	}
	.account {
		display: grid;
		grid-template-columns: auto 1fr;
		gap: 0.375rem 1.25rem;
		margin: 0;
	}
	.account dt {
		color: #55555f;
	}
	.account dd {
		margin: 0;
	}
	.authority-note {
		color: #55555f;
		font-size: 0.875rem;
	}
</style>
