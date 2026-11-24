import { n as head } from "../../../../chunks/server2.js";
import "../../../../chunks/auth.js";
import "../../../../chunks/index-server.js";
import "../../../../chunks/navigation.js";
//#region src/routes/auth/callback/+page.svelte
function _page($$renderer, $$props) {
	$$renderer.component(($$renderer) => {
		head("3cfahf", $$renderer, ($$renderer) => {
			$$renderer.title(($$renderer) => {
				$$renderer.push(`<title>Horizon 2 · Signing in</title>`);
			});
		});
		$$renderer.push(`<main><h1>Horizon 2</h1> `);
		$$renderer.push(`<!--[-1--><p role="status">Completing sign-in…</p>`);
		$$renderer.push(`<!--]--></main>`);
	});
}
//#endregion
export { _page as default };

//# sourceMappingURL=_page.svelte.js.map