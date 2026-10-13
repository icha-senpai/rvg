<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { completeSignin } from '#lib/auth.ts';

	let error = $state<string | null>(null);

	onMount(() => {
		const callbackUrl = window.location.href;
		void goto(window.location.pathname, { replace: true, shallow: true });
		completeSignin(callbackUrl)
			.then(() => goto('/', { replace: true }))
			.catch((caught: unknown) => {
				error =
					caught instanceof Error
						? caught.message
						: 'Citizen iD sign-in could not be completed. Please try again.';
			});
	});
</script>

<svelte:head>
	<title>Horizon 2 · Signing in</title>
</svelte:head>

<main>
	<h1>Horizon 2</h1>
	{#if error}
		<p role="alert">{error}</p>
		<p><a href="/">Back to sign in</a></p>
	{:else}
		<p role="status">Completing sign-in…</p>
	{/if}
</main>
