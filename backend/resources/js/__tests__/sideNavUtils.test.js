import test from 'node:test'
import assert from 'node:assert/strict'

import {
  buildArchiveCategoryChildren,
  itemChildListIsOpen,
  itemChildrenAreOpen,
  itemIsOpen,
  itemRowTogglesChildren,
  navBadgeLabel,
  normalizeNavHref,
} from '../sideNavUtils.js'

test('normalizeNavHref returns a path and query for absolute or relative hrefs', () => {
  assert.equal(normalizeNavHref('https://example.com/archive/topic?a=1'), '/archive/topic?a=1')
  assert.equal(normalizeNavHref('/archive/topic?a=1'), '/archive/topic?a=1')
  assert.equal(normalizeNavHref(''), '')
})

test('buildArchiveCategoryChildren sorts categories and marks nested active states', () => {
  const categories = [
    {
      id: 2,
      name: 'Zulu',
      sort_order: 20,
      href: '/archive/category/zulu',
      visible_entries_count: 1,
      entries: [],
      topics: [],
    },
    {
      id: 1,
      name: 'Alpha',
      sort_order: 10,
      href: '/archive/category/alpha',
      visible_entries_count: 3,
      entries: [
        { id: 9, title: 'Quick Start', href: '/archive/category/alpha/quick-start' },
      ],
      topics: [
        {
          id: 5,
          title: 'Flight Ops',
          href: '/archive/flight-ops',
          visible_entries_count: 2,
          entries: [
            { id: 11, title: 'Topic Entry', href: '/archive/flight-ops/topic-entry' },
          ],
        },
      ],
    },
  ]

  const result = buildArchiveCategoryChildren(categories, '/archive/flight-ops/topic-entry')

  assert.equal(result[0].label, 'Alpha')
  assert.equal(result[1].label, 'Zulu')
  assert.equal(result[0].isActive, true)
  assert.equal(result[0].topics[0].isActive, true)
  assert.equal(result[0].topics[0].entries[0].isActive, true)
  assert.equal(result[0].entries[0].isActive, false)
  assert.equal(result[0].meta, '3')
  assert.equal(result[0].topics[0].meta, '2')
})

test('navBadgeLabel returns null for empty badges and caps large counts', () => {
  assert.equal(navBadgeLabel(0), null)
  assert.equal(navBadgeLabel(-2), null)
  assert.equal(navBadgeLabel(12), '12')
  assert.equal(navBadgeLabel(120), '99+')
})

test('archive and admin dashboard rows toggle children while others do not', () => {
  assert.equal(itemRowTogglesChildren({ key: 'archive', children: [{}] }), true)
  assert.equal(itemRowTogglesChildren({ key: 'admin_dashboard', children: [{}] }), true)
  assert.equal(itemRowTogglesChildren({ key: 'members', children: [{}] }), false)
})

test('item open helpers respect active state plus manual expand and collapse overrides', () => {
  const archiveItem = {
    key: 'archive',
    isActive: false,
    children: [{ key: 'child-1' }],
  }

  assert.equal(itemIsOpen(archiveItem), false)
  assert.equal(itemIsOpen(archiveItem, new Set(['archive'])), true)
  assert.equal(itemChildListIsOpen(archiveItem, new Set(['archive'])), true)

  const topicItem = {
    key: 'archive-topic-1',
    isActive: true,
    entries: [{ key: 'entry-1' }],
  }

  assert.equal(itemIsOpen(topicItem), true)
  assert.equal(itemChildrenAreOpen(topicItem), true)
  assert.equal(itemIsOpen(topicItem, new Set(), new Set(['archive-topic-1'])), false)
  assert.equal(itemChildrenAreOpen(topicItem, new Set(), new Set(['archive-topic-1'])), false)
})
