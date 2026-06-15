import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from 'jsdom'

import { normalizeRichTextHtml } from '../richText.js'

const originalDocument = global.document
const originalHTMLElement = global.HTMLElement

function withDom(callback) {
  const { window } = new JSDOM('<!doctype html><html><body></body></html>')

  global.document = window.document
  global.HTMLElement = window.HTMLElement

  try {
    return callback(window.document)
  } finally {
    global.document = originalDocument
    global.HTMLElement = originalHTMLElement
    window.close()
  }
}

test('normalizeRichTextHtml leaves non-callout content untouched', () => {
  const html = '<p>Plain content</p>'

  assert.equal(normalizeRichTextHtml(html), html)
})

test('normalizeRichTextHtml normalizes custom callouts and clears stale inline surface styles', () => {
  withDom(document => {
    const normalized = normalizeRichTextHtml(
      '<div data-type="hz-rte-callout" class="hz-rte-callout extra" data-accent-color="#AbC" style="background:red;background-color:blue;border-color:green">Alert</div>'
    )

    const container = document.createElement('div')
    container.innerHTML = normalized

    const callout = container.querySelector('[data-type="hz-rte-callout"]')

    assert.ok(callout)
    assert.equal(callout.className, 'hz-rte-callout hz-rte-callout-custom extra')
    assert.equal(callout.getAttribute('data-tone'), 'custom')
    assert.equal(callout.getAttribute('data-accent-color'), '#aabbcc')
    assert.equal(callout.style.getPropertyValue('--hz-callout-custom-color').trim(), '#aabbcc')
    assert.equal(callout.style.getPropertyValue('background').trim(), '')
    assert.equal(callout.style.getPropertyValue('background-color').trim(), '')
    assert.match(callout.style.getPropertyValue('border-color'), /color-mix/i)
    assert.match(callout.style.getPropertyValue('border-color'), /42%/)
  })
})

test('normalizeRichTextHtml derives tone from classes and removes stale accent styling when no custom color remains', () => {
  withDom(document => {
    const normalized = normalizeRichTextHtml(
      '<div data-type="hz-rte-callout" class="foo hz-rte-callout hz-rte-callout-red" data-tone="green" data-accent-color="not-a-color" style="background:red;border-color:green">Alert</div>'
    )

    const container = document.createElement('div')
    container.innerHTML = normalized

    const callout = container.querySelector('[data-type="hz-rte-callout"]')

    assert.ok(callout)
    assert.equal(callout.className, 'hz-rte-callout hz-rte-callout-green foo')
    assert.equal(callout.getAttribute('data-tone'), 'green')
    assert.equal(callout.hasAttribute('data-accent-color'), false)
    assert.equal(callout.style.getPropertyValue('--hz-callout-custom-color').trim(), '')
    assert.equal(callout.hasAttribute('data-accent-color'), false)
  })
})
