import { defineEnvVars } from "@sveltejs/kit/env";
import { handle_issues, validate } from "@sveltejs/kit/internal/env";
//#region src/env.ts
var variables = defineEnvVars({
	PUBLIC_SPACETIME_URI: {
		public: true,
		schema: (value) => value
	},
	PUBLIC_SPACETIME_DATABASE: {
		public: true,
		schema: (value) => value
	}
});
//#endregion
//#region .svelte-kit/generated/build/env/config.js
var issues = {};
var dynamic_private_env = {};
var explicit_public_env = {};
var rendered_env = {};
handle_issues(issues);
function set_env(env) {
	const issues = {};
	const PUBLIC_SPACETIME_URI = validate(variables, env.PUBLIC_SPACETIME_URI, "PUBLIC_SPACETIME_URI", issues);
	explicit_public_env.PUBLIC_SPACETIME_URI = PUBLIC_SPACETIME_URI;
	rendered_env.PUBLIC_SPACETIME_URI = PUBLIC_SPACETIME_URI;
	const PUBLIC_SPACETIME_DATABASE = validate(variables, env.PUBLIC_SPACETIME_DATABASE, "PUBLIC_SPACETIME_DATABASE", issues);
	explicit_public_env.PUBLIC_SPACETIME_DATABASE = PUBLIC_SPACETIME_DATABASE;
	rendered_env.PUBLIC_SPACETIME_DATABASE = PUBLIC_SPACETIME_DATABASE;
	handle_issues(issues);
}
//#endregion
export { variables as a, set_env as i, explicit_public_env as n, rendered_env as r, dynamic_private_env as t };

//# sourceMappingURL=config.js.map