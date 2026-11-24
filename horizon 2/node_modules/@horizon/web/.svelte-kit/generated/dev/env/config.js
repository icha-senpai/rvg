import { variables } from "../../../../src/env.ts";
import { validate, handle_issues } from '@sveltejs/kit/internal/env';

const issues = {};

export { variables }

export const dynamic_private_env = {};

export const explicit_public_env = {};

export const rendered_env = {};

handle_issues(issues);

export function set_env(env) {
	const issues = {};
	const PUBLIC_SPACETIME_URI = validate(variables, env.PUBLIC_SPACETIME_URI, "PUBLIC_SPACETIME_URI", issues);
	explicit_public_env.PUBLIC_SPACETIME_URI = PUBLIC_SPACETIME_URI;
	rendered_env.PUBLIC_SPACETIME_URI = PUBLIC_SPACETIME_URI;
	const PUBLIC_SPACETIME_DATABASE = validate(variables, env.PUBLIC_SPACETIME_DATABASE, "PUBLIC_SPACETIME_DATABASE", issues);
	explicit_public_env.PUBLIC_SPACETIME_DATABASE = PUBLIC_SPACETIME_DATABASE;
	rendered_env.PUBLIC_SPACETIME_DATABASE = PUBLIC_SPACETIME_DATABASE;
	handle_issues(issues);
}

set_env({PUBLIC_SPACETIME_URI:"ws://127.0.0.1:3000",PUBLIC_SPACETIME_DATABASE:"horizon-2-owned"});