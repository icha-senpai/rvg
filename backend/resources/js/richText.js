const VALID_CALLOUT_TONES = new Set(['blue', 'cyan', 'magenta', 'orange', 'green', 'red'])

function normalizeHexColor(value) {
  const raw = String(value ?? '').trim()

  if (!raw) {
    return ''
  }

  const match = raw.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i)
  if (!match) {
    return ''
  }

  const [, hex] = match
  if (hex.length === 3) {
    return `#${hex.split('').map(char => char + char).join('').toLowerCase()}`
  }

  return `#${hex.toLowerCase()}`
}

function resolveCalloutTone(element, accentColor) {
  if (accentColor) {
    return 'custom'
  }

  const toneAttribute = String(element.getAttribute('data-tone') ?? '').trim().toLowerCase()
  if (VALID_CALLOUT_TONES.has(toneAttribute)) {
    return toneAttribute
  }

  const toneClass = [...element.classList]
    .map(className => className.match(/^hz-rte-callout-([a-z]+)$/i)?.[1]?.toLowerCase() ?? null)
    .find(value => VALID_CALLOUT_TONES.has(value))

  return toneClass ?? 'blue'
}

export function normalizeRichTextHtml(value) {
  const html = String(value ?? '')

  if (!html || !html.includes('hz-rte-callout') || typeof document === 'undefined') {
    return html
  }

  const container = document.createElement('div')
  container.innerHTML = html

  container.querySelectorAll('[data-type="hz-rte-callout"]').forEach(node => {
    if (!(node instanceof HTMLElement)) {
      return
    }

    const accentColor = normalizeHexColor(node.getAttribute('data-accent-color'))
    const tone = resolveCalloutTone(node, accentColor)
    const remainingClasses = [...node.classList].filter(className => {
      return className !== 'hz-rte-callout' && !className.startsWith('hz-rte-callout-')
    })

    node.className = ['hz-rte-callout', `hz-rte-callout-${tone}`, ...remainingClasses].join(' ')
    node.setAttribute('data-tone', tone)

    if (accentColor) {
      node.setAttribute('data-accent-color', accentColor)
    } else {
      node.removeAttribute('data-accent-color')
    }

    node.style.removeProperty('background')
    node.style.removeProperty('background-color')
    node.style.removeProperty('border-color')
    node.style.removeProperty('--hz-callout-custom-color')

    if (accentColor) {
      node.style.setProperty('--hz-callout-custom-color', accentColor)
      node.style.setProperty('border-color', `color-mix(in srgb, ${accentColor} 42%, transparent)`)
    }

    if (!node.getAttribute('style')?.trim()) {
      node.removeAttribute('style')
    }
  })

  return container.innerHTML
}
