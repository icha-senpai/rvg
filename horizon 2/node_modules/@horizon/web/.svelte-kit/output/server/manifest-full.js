export const manifest = (() => {
function __memo(fn) {
	let value;
	return () => value ??= (value = fn());
}

return {
	app_dir: "_app",
	app_path: "_app",
	assets: new Set(["favicon.svg","robots.txt"]),
	mime_types: {".svg":"image/svg+xml",".txt":"text/plain"},
	client: {start:"_app/immutable/entry/start.BkPMISk8.js",app:"_app/immutable/entry/app.B2HlH1Wj.js",imports:["_app/immutable/entry/start.BkPMISk8.js","_app/immutable/entry/payload.DSmR2FwN.js","_app/immutable/chunks/BaNbYf_w.js","_app/immutable/chunks/D5kxlTis.js","_app/immutable/chunks/B8ERkmie.js","_app/immutable/chunks/Cxe6-Q_y.js","_app/immutable/chunks/D51ipGYN.js","_app/immutable/chunks/DYrviY11.js","_app/immutable/entry/app.B2HlH1Wj.js"],stylesheets:[],fonts:[],uses_env_dynamic_public:true},
	
	nodes: [
		__memo(() => import('./nodes/0.js')),
		__memo(() => import('./nodes/1.js')),
		__memo(() => import('./nodes/2.js')),
		__memo(() => import('./nodes/3.js'))
	],
	remotes: {
		
	},
	routes: [
		{
			id: "/",
			pattern: /^\/$/,
			params: [],
			page: { layouts: [0,], errors: [1,], leaf: 2 },
			endpoint: null
		},
		{
			id: "/auth/callback",
			pattern: /^\/auth\/callback\/?$/,
			params: [],
			page: { layouts: [0,], errors: [1,], leaf: 3 },
			endpoint: null
		}
	],
	prerendered_routes: new Set([]),
	matchers: async () => {
		return {};
	},
	server_assets: {}
}
})();
