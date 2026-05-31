import { normalizeOrgRoleSlug } from '@/roleColors'

const DIRECTOR_LIKE_ROLE_SLUGS = ['director', 'tech_director']
const OFFICER_ROLE_SLUGS = ['lieutenant', 'cit', 'commander', 'wing_commander', 'admiral', 'grand_admiral']
const COMMANDER_PLUS_ROLE_SLUGS = ['wing_commander', 'admiral', 'grand_admiral']
const ADMIRAL_PLUS_ROLE_SLUGS = ['admiral', 'grand_admiral']

export function getRoleSlugs(user) {
  return (user?.roles ?? [])
    .map(role => normalizeOrgRoleSlug(typeof role === 'string' ? role : role?.slug))
    .filter(Boolean)
}

export function hasAnyRole(user, roleSlugs) {
  const userRoleSlugs = getRoleSlugs(user)
  return roleSlugs.some(roleSlug => userRoleSlugs.includes(normalizeOrgRoleSlug(roleSlug)))
}

export function isDirectorLike(user) {
  return hasAnyRole(user, DIRECTOR_LIKE_ROLE_SLUGS)
}

export function isOfficer(user) {
  return isDirectorLike(user) || hasAnyRole(user, OFFICER_ROLE_SLUGS)
}

export function isCommanderPlus(user) {
  return isDirectorLike(user) || hasAnyRole(user, COMMANDER_PLUS_ROLE_SLUGS)
}

export function isAdmiralPlus(user) {
  return isDirectorLike(user) || hasAnyRole(user, ADMIRAL_PLUS_ROLE_SLUGS)
}

export function getActiveSquadronMembership(user, squadronId) {
  const numericSquadronId = Number(squadronId)
  if (!Number.isFinite(numericSquadronId) || !numericSquadronId) {
    return null
  }

  return (user?.squadrons ?? []).find(squadron => {
    return Number(squadron?.id) === numericSquadronId && squadron?.pivot?.membership_status === 'active'
  }) ?? null
}

export function canCreateOperation(user) {
  return isOfficer(user)
}

export function canUploadOperationImages(user) {
  return isOfficer(user)
}

export function canSaveSquadronTemplate(user, squadronId) {
  if (isDirectorLike(user)) {
    return true
  }

  return isOfficer(user) && !!getActiveSquadronMembership(user, squadronId)
}

export function canEditSquadronEmblem(user, viewerMembership) {
  if (isDirectorLike(user)) {
    return true
  }

  if (viewerMembership?.is_leader === true) {
    return true
  }

  return isOfficer(user) && viewerMembership?.membership_status === 'active'
}
