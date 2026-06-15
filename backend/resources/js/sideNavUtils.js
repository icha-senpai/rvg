export function normalizeNavHref(href, origin = 'http://localhost') {
  if (!href) return ''

  try {
    const resolved = new URL(href, origin)
    return `${resolved.pathname}${resolved.search}`
  } catch {
    return String(href)
  }
}

export function buildArchiveCategoryChildren(categories, currentUrl, origin = 'http://localhost') {
  const url = currentUrl ?? ''

  return (categories ?? []).map(category => {
    const categoryHref = normalizeNavHref(category?.href, origin)
    const directEntries = (category?.entries ?? []).map(entry => {
      const entryHref = normalizeNavHref(entry?.href, origin)

      return {
        key: `archive-category-entry-${entry.id}`,
        label: entry.title,
        href: entry.href,
        isActive: url === entryHref || url.startsWith(`${entryHref}?`),
      }
    })

    const topics = (category?.topics ?? []).map(topic => {
      const topicHref = normalizeNavHref(topic?.href, origin)
      const topicEntries = (topic?.entries ?? []).map(entry => {
        const entryHref = normalizeNavHref(entry?.href, origin)

        return {
          key: `archive-entry-${entry.id}`,
          label: entry.title,
          href: entry.href,
          isActive: url === entryHref || url.startsWith(`${entryHref}?`),
        }
      })

      const topicIsActive = url === topicHref
        || url.startsWith(`${topicHref}/`)
        || url.startsWith(`${topicHref}?`)
        || topicEntries.some(entry => entry.isActive)

      return {
        key: `archive-topic-${topic.id}`,
        label: topic.title,
        href: topic.href,
        isActive: topicIsActive,
        meta: topic.visible_entries_count ? `${topic.visible_entries_count}` : null,
        entries: topicEntries,
      }
    })

    const isActive = url === categoryHref
      || url.startsWith(`${categoryHref}/`)
      || url.startsWith(`${categoryHref}?`)
      || directEntries.some(entry => entry.isActive)
      || topics.some(topic => topic.isActive)

    return {
      key: `archive-category-${category.id}`,
      label: category.name,
      href: category.href,
      sortOrder: Number(category.sort_order ?? Number.MAX_SAFE_INTEGER),
      topics,
      entries: directEntries,
      visibleEntriesCount: Number(category.visible_entries_count ?? 0),
      isActive,
      meta: category.visible_entries_count ? `${category.visible_entries_count}` : null,
    }
  }).sort((left, right) => {
    if (left.sortOrder !== right.sortOrder) {
      return left.sortOrder - right.sortOrder
    }

    return left.label.localeCompare(right.label)
  })
}

export function navBadgeLabel(count) {
  const numericCount = Number(count ?? 0)

  if (!Number.isFinite(numericCount) || numericCount <= 0) {
    return null
  }

  return numericCount > 99 ? '99+' : String(numericCount)
}

export function itemHasOpenChildren(item) {
  return Boolean(item?.children?.length)
}

export function itemRowTogglesChildren(item) {
  return ['archive', 'admin_dashboard'].includes(item?.key) && itemHasOpenChildren(item)
}

export function itemIsOpen(item, manuallyExpandedKeys = new Set(), manuallyCollapsedKeys = new Set()) {
  const hasNestedContent = Boolean(item?.children?.length || item?.entries?.length || item?.topics?.length)

  if (!hasNestedContent) {
    return false
  }

  if (manuallyCollapsedKeys.has(item.key)) {
    return false
  }

  return Boolean(item?.isActive) || manuallyExpandedKeys.has(item.key)
}

export function itemChildrenAreOpen(item, manuallyExpandedKeys = new Set(), manuallyCollapsedKeys = new Set()) {
  return Boolean((item?.entries?.length || item?.topics?.length) && itemIsOpen(item, manuallyExpandedKeys, manuallyCollapsedKeys))
}

export function itemChildListIsOpen(item, manuallyExpandedKeys = new Set(), manuallyCollapsedKeys = new Set()) {
  return Boolean(item?.children?.length && itemIsOpen(item, manuallyExpandedKeys, manuallyCollapsedKeys))
}
