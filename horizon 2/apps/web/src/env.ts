import { defineEnvVars } from '@sveltejs/kit/env';

export const variables = defineEnvVars({
	PUBLIC_SPACETIME_URI: {
		public: true,
		schema: (value) => value
	},
	PUBLIC_SPACETIME_DATABASE: {
		public: true,
		schema: (value) => value
	}
});
