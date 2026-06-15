import test from 'node:test'
import assert from 'node:assert/strict'

import {
  formatOrgRoleLabel,
  getHighestOrgRoleSlug,
  getOrgRoleColor,
  normalizeOrgRoleSlug,
} from '../roleColors.js'

test('normalizeOrgRoleSlug trims, lowercases, and normalizes separators', () => {
  assert.equal(normalizeOrgRoleSlug(' Wing Commander '), 'wing_commander')
  assert.equal(normalizeOrgRoleSlug('tech-director'), 'tech_director')
  assert.equal(normalizeOrgRoleSlug(null), '')
})

test('getHighestOrgRoleSlug prefers the highest explicit role over fallback rank', () => {
  const roles = [
    { slug: 'member' },
    { slug: 'Commander' },
    { slug: 'tech-director' },
  ]

  assert.equal(getHighestOrgRoleSlug(roles, 'lieutenant'), 'tech_director')
})

test('getHighestOrgRoleSlug falls back to a normalized rank when no recognized roles exist', () => {
  assert.equal(getHighestOrgRoleSlug([{ slug: 'guest' }], 'Wing Commander'), 'wing_commander')
  assert.equal(getHighestOrgRoleSlug([], 'Unknown Rank'), null)
})

test('getOrgRoleColor returns the mapped hex code for normalized role slugs', () => {
  assert.equal(getOrgRoleColor('Tech Director'), '#71368a')
  assert.equal(getOrgRoleColor('member'), '#40b2ff')
  assert.equal(getOrgRoleColor('mystery-role'), null)
})

test('formatOrgRoleLabel converts normalized slugs into readable labels', () => {
  assert.equal(formatOrgRoleLabel('tech_director'), 'Tech Director')
  assert.equal(formatOrgRoleLabel('wing-commander'), 'Wing Commander')
  assert.equal(formatOrgRoleLabel(''), '')
})
