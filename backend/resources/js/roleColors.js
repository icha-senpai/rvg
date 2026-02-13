export const ORG_ROLE_COLORS = {
  director: '#ff1a00',
  tech_director: '#71368a',
  grand_admiral: '#ba0202',
  admiral: '#fa530b',
  wing_commander: '#f58925',
  commander: '#f1c40f',
  cit: '#d1ac18',
  lieutenant: '#1ae7f1',
  member: '#40b2ff',
}

const ORG_ROLE_PRIORITY = [
  'director',
  'tech_director',
  'grand_admiral',
  'admiral',
  'wing_commander',
  'commander',
  'cit',
  'lieutenant',
  'member',
]

export function normalizeOrgRoleSlug(slug) {
  return String(slug ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_')
}

export function getHighestOrgRoleSlug(roles, fallbackRank) {
  const roleSlugs = (roles ?? [])
    .map(r => (typeof r === 'string' ? r : r?.slug))
    .map(normalizeOrgRoleSlug)
    .filter(Boolean)

  for (const slug of ORG_ROLE_PRIORITY) {
    if (roleSlugs.includes(slug)) return slug
  }

  const rankSlug = normalizeOrgRoleSlug(fallbackRank)
  if (rankSlug && ORG_ROLE_PRIORITY.includes(rankSlug)) return rankSlug

  return null
}

export function getOrgRoleColor(slug) {
  const key = normalizeOrgRoleSlug(slug)
  return ORG_ROLE_COLORS[key] ?? null
}

export function formatOrgRoleLabel(slug) {
  const key = normalizeOrgRoleSlug(slug)
  if (!key) return ''

  return key
    .split('_')
    .map(part => (part ? part.charAt(0).toUpperCase() + part.slice(1) : ''))
    .join(' ')
}
