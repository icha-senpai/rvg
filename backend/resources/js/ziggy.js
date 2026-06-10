const fallbackZiggy = {
  url: '',
  port: null,
  defaults: {},
  routes: {},
}

const Ziggy = typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined'
  ? window.Ziggy
  : fallbackZiggy

export { Ziggy }
