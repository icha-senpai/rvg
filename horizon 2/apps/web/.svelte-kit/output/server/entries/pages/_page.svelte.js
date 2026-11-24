import { a as unsubscribe_stores, c as attr, i as store_get, n as head, u as escape_html } from "../../chunks/server2.js";
import { t as authState } from "../../chunks/auth.js";
import "../../chunks/index-server.js";
//#region src/routes/+page.svelte
function _page($$renderer, $$props) {
	$$renderer.component(($$renderer) => {
		var $$store_subs;
		head("1uha8ag", $$renderer, ($$renderer) => {
			$$renderer.title(($$renderer) => {
				$$renderer.push(`<title>Horizon 2</title>`);
			});
		});
		$$renderer.push(`<main class="svelte-1uha8ag"><h1>Horizon 2</h1> <p class="staging-note svelte-1uha8ag">Citizen iD staging sign-in proof. Local SpaceTimeDB only.</p> <section class="panel svelte-1uha8ag" aria-live="polite">`);
		if (store_get($$store_subs ??= {}, "$authState", authState).notice) $$renderer.push(`<!--[0--><p role="status">${escape_html(store_get($$store_subs ??= {}, "$authState", authState).notice)}</p>`);
		else $$renderer.push("<!--[-1-->");
		$$renderer.push(`<!--]--> `);
		if (store_get($$store_subs ??= {}, "$authState", authState).error) $$renderer.push(`<!--[0--><p role="alert">${escape_html(store_get($$store_subs ??= {}, "$authState", authState).error)}</p>`);
		else $$renderer.push("<!--[-1-->");
		$$renderer.push(`<!--]--> `);
		if (store_get($$store_subs ??= {}, "$authState", authState).status === "signed_out") $$renderer.push(`<!--[0--><button type="button" class="svelte-1uha8ag">Sign in with Citizen iD</button>`);
		else if (store_get($$store_subs ??= {}, "$authState", authState).status === "signing_in") $$renderer.push(`<!--[1--><p role="status">Redirecting to Citizen iD…</p>`);
		else if (store_get($$store_subs ??= {}, "$authState", authState).status === "connecting") $$renderer.push(`<!--[2--><p role="status">Connecting to Horizon…</p>`);
		else if (store_get($$store_subs ??= {}, "$authState", authState).status === "reconnecting") $$renderer.push(`<!--[3--><p role="status">Reconnecting… Showing your last synced account. Actions are paused until live data returns.</p>`);
		else if (store_get($$store_subs ??= {}, "$authState", authState).status === "disconnected") {
			$$renderer.push("<!--[4-->");
			if (store_get($$store_subs ??= {}, "$authState", authState).canRetry) $$renderer.push(`<!--[0--><button type="button" class="svelte-1uha8ag">Retry connection</button>`);
			else $$renderer.push("<!--[-1-->");
			$$renderer.push(`<!--]--> <button type="button" class="svelte-1uha8ag">Sign in with Citizen iD</button>`);
		} else $$renderer.push("<!--[-1-->");
		$$renderer.push(`<!--]--> `);
		if ((store_get($$store_subs ??= {}, "$authState", authState).status === "ready" || store_get($$store_subs ??= {}, "$authState", authState).status === "reconnecting") && store_get($$store_subs ??= {}, "$authState", authState).account) {
			$$renderer.push("<!--[0-->");
			const account = store_get($$store_subs ??= {}, "$authState", authState).account;
			$$renderer.push(`<dl class="account svelte-1uha8ag"><dt class="svelte-1uha8ag">Horizon ID</dt> <dd class="svelte-1uha8ag">${escape_html(account.id)}</dd> <dt class="svelte-1uha8ag">Account status</dt> <dd class="svelte-1uha8ag">${escape_html(account.status.tag === "Pending" ? "Pending" : account.status.tag)}</dd> <dt class="svelte-1uha8ag">Display name</dt> <dd class="svelte-1uha8ag">${escape_html(account.displayName ?? account.preferredUsername ?? "Not shared")}</dd> <dt class="svelte-1uha8ag">RSI handle</dt> <dd class="svelte-1uha8ag">${escape_html(account.rsiHandle ?? "No RSI profile shared")}</dd> <dt class="svelte-1uha8ag">RSI verified</dt> <dd class="svelte-1uha8ag">${escape_html(account.rsiVerified ? "Verified by Citizen iD" : "Not verified")}</dd> <dt class="svelte-1uha8ag">RSI citizen ID</dt> <dd class="svelte-1uha8ag">${escape_html(account.rsiCitizenId ?? "Not shared")}</dd> <dt class="svelte-1uha8ag">RSI spectrum ID</dt> <dd class="svelte-1uha8ag">${escape_html(account.rsiSpectrumId ?? "Not shared")}</dd> <dt class="svelte-1uha8ag">Discord account</dt> <dd class="svelte-1uha8ag">${escape_html(account.discordAccountId ?? "No Discord account linked")}</dd> <dt class="svelte-1uha8ag">Primary RSI organization</dt> <dd class="svelte-1uha8ag">${escape_html(account.rsiPrimaryOrg ?? "Not shared")}</dd> <dt class="svelte-1uha8ag">Public RSI organizations</dt> <dd class="svelte-1uha8ag">${escape_html((account.rsiPublicOrgs ?? []).join(", ") || "None shared")}</dd> <dt class="svelte-1uha8ag">Last seen</dt> <dd class="svelte-1uha8ag"><time${attr("datetime", account.lastSeenAt.toISOString())}>${escape_html(account.lastSeenAt.toDate().toLocaleString(void 0, {
				dateStyle: "medium",
				timeStyle: "short"
			}))}</time></dd></dl> <p class="authority-note svelte-1uha8ag">Snapshot data from Citizen iD. Your Horizon account is pending — no ranks, admin rights, or
				squadron membership are granted by this sign-in.</p> <button type="button" class="svelte-1uha8ag">Sign out of Horizon</button> <p class="authority-note svelte-1uha8ag">Citizen iD may keep you signed in on their site.</p>`);
		} else $$renderer.push("<!--[-1-->");
		$$renderer.push(`<!--]--></section></main>`);
		if ($$store_subs) unsubscribe_stores($$store_subs);
	});
}
//#endregion
export { _page as default };

//# sourceMappingURL=_page.svelte.js.map