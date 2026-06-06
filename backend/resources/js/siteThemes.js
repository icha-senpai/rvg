export const coreSiteThemes = [
  {
    value: 'horizon',
    label: 'Horizon',
    description: 'The current command-deck look with the existing Horizon blues and glows.',
    dark: '#0b0d14',
    primary: '#1f5993',
    secondary: '#4338ca',
    text: '#ede9fe',
  },
  {
    value: 'dark',
    label: 'Dark',
    description: 'A blackish theme with quieter surfaces and less of the blue command tint.',
    dark: '#05070c',
    primary: '#2d6cdf',
    secondary: '#7ea6ff',
    text: '#f8fafc',
  },
]

const manufacturerPalettes = [
  {
    value: 'aegis',
    label: 'Aegis Dynamics',
    description: 'Deep military crimson with a heavier command-deck feel.',
    primary: '#8B1E2D',
    secondary: '#C73545',
    dark: '#16080B',
    text: '#F4CDD2',
  },
  {
    value: 'anvil',
    label: 'Anvil Aerospace',
    description: 'Cool steel blue with naval-industrial restraint.',
    primary: '#3F6F8F',
    secondary: '#8AA9BF',
    dark: '#07131A',
    text: '#D8ECF8',
  },
  {
    value: 'argo',
    label: 'Argo Astronautics',
    description: 'Industrial orange with warm utility-bay heat.',
    primary: '#E9781E',
    secondary: '#F6A548',
    dark: '#1C0E04',
    text: '#FFE0B4',
  },
  {
    value: 'crusader',
    label: 'Crusader Industries',
    description: 'Clean sky-cyan with a bright aerospace glow.',
    primary: '#41B7D8',
    secondary: '#B7F3FF',
    dark: '#061419',
    text: '#DDF8FF',
  },
  {
    value: 'drake',
    label: 'Drake Interplanetary',
    description: 'Gritty yellow-ochre with a rougher frontier edge.',
    primary: '#C88A2A',
    secondary: '#F0C15C',
    dark: '#171006',
    text: '#FFE1B2',
  },
  {
    value: 'origin',
    label: 'Origin Jumpworks',
    description: 'Luxury champagne tones with a cleaner premium shell.',
    primary: '#D8C28A',
    secondary: '#FFFFFF',
    dark: '#18140B',
    text: '#FFF2C2',
  },
  {
    value: 'misc',
    label: 'MISC',
    description: 'Trade-green utility with softer shipyard highlights.',
    primary: '#4FB06D',
    secondary: '#9EE6B3',
    dark: '#07160C',
    text: '#D9FFE3',
  },
  {
    value: 'mirai',
    label: 'Mirai',
    description: 'High-energy magenta with bright racer cyan support.',
    primary: '#FF2FA3',
    secondary: '#7DF9FF',
    dark: '#1B0613',
    text: '#FFD7F0',
  },
  {
    value: 'rsi',
    label: 'Roberts Space Industries',
    description: 'Classic RSI command blue with sharper fleet accents.',
    primary: '#2D6CDF',
    secondary: '#7EA6FF',
    dark: '#071126',
    text: '#D9E6FF',
  },
  {
    value: 'consolidated_outland',
    label: 'Consolidated Outland',
    description: 'Violet and mint with a sleek frontier-tech blend.',
    primary: '#7C5CFF',
    secondary: '#34E0A1',
    dark: '#100B24',
    text: '#E5DEFF',
  },
  {
    value: 'aopoa',
    label: 'Aopoa',
    description: 'Alien cyan with vivid purple contrast.',
    primary: '#19D3C5',
    secondary: '#B164FF',
    dark: '#061817',
    text: '#D9FFFA',
  },
  {
    value: 'banu',
    label: 'Banu',
    description: 'Mercantile teal with warm gold support lighting.',
    primary: '#00B894',
    secondary: '#F6C85F',
    dark: '#061511',
    text: '#D7FFF5',
  },
  {
    value: 'esperia',
    label: 'Esperia',
    description: 'Antique bronze with quieter museum-dark plating.',
    primary: '#9B6A3C',
    secondary: '#D9B382',
    dark: '#140D07',
    text: '#F7DFC3',
  },
  {
    value: 'gatac',
    label: 'Gatac Manufacture',
    description: 'Soft jade with a refined lilac glow.',
    primary: '#6ED6A5',
    secondary: '#C9A7FF',
    dark: '#071611',
    text: '#E0FFF1',
  },
  {
    value: 'kruger',
    label: 'Kruger Intergalactic',
    description: 'Brushed alloy silver with clean shuttle styling.',
    primary: '#C0C7D1',
    secondary: '#F2F5F8',
    dark: '#101317',
    text: '#EEF4FA',
  },
  {
    value: 'greycat',
    label: 'Greycat Industrial',
    description: 'Construction yellow balanced by industrial graphite.',
    primary: '#F0C24B',
    secondary: '#6F747C',
    dark: '#17140A',
    text: '#FFF1B8',
  },
  {
    value: 'tumbril',
    label: 'Tumbril Land Systems',
    description: 'Olive armor tones with rugged ground-force weight.',
    primary: '#5F6F3A',
    secondary: '#B4B86A',
    dark: '#0F1208',
    text: '#E7ECC1',
  },
]

export const manufacturerSiteThemes = manufacturerPalettes

export const siteThemeOptions = [
  ...coreSiteThemes,
  ...manufacturerSiteThemes,
]

export const siteThemeGroups = [
  {
    key: 'core',
    label: 'Core Themes',
    description: 'The base themes for Horizon itself.',
    options: coreSiteThemes,
  },
  {
    key: 'manufacturers-dark',
    label: 'Manufacturer Dark Themes',
    description: 'Star Citizen manufacturer-inspired dark themes.',
    options: manufacturerSiteThemes,
  },
]

export const allowedSiteThemeValues = siteThemeOptions.map((option) => option.value)

const allowedSiteThemeSet = new Set(allowedSiteThemeValues)

export function normalizeSiteTheme(theme) {
  return allowedSiteThemeSet.has(theme) ? theme : 'horizon'
}
