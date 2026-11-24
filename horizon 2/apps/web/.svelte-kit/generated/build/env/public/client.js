import { payload } from "../../../../../../../node_modules/@sveltejs/kit/src/runtime/client/payload.js";
const env = payload.env;

export const PUBLIC_SPACETIME_URI = env.PUBLIC_SPACETIME_URI;
export const PUBLIC_SPACETIME_DATABASE = env.PUBLIC_SPACETIME_DATABASE;
