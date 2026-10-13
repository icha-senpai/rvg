import { expect, test } from '@playwright/test';

test('routeWebSocket observes connections', async ({ page }) => {
	let hits = 0;
	await page.routeWebSocket(/127\.0\.0\.1:3000/, async (ws) => {
		hits += 1;
		try {
			const server = await ws.connectToServer();
			ws.onMessage((m) => server.send(m));
			server.onMessage((m) => ws.send(m));
		} catch {
			ws.close();
		}
	});
	await page.goto('http://127.0.0.1:5273/');
	await page.evaluate(() => {
		const socket = new WebSocket('ws://127.0.0.1:3000/v1/ping');
		socket.onopen = () => socket.close();
	});
	await expect.poll(() => hits, { timeout: 10000 }).toBeGreaterThan(0);
});
