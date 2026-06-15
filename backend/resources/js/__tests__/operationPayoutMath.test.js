import test from 'node:test'
import assert from 'node:assert/strict'

import {
  buildEvenSplitAmounts,
  calculateDistributableAmount,
  calculateHorizonReserve,
  calculateReserveBaseAmount,
  normalizeWholeNumberField,
  parsePositiveWholeNumber,
  sumPositiveNonOrganizationAmounts,
} from '../operationPayoutMath.js'

test('parsePositiveWholeNumber only accepts positive whole values', () => {
  assert.equal(parsePositiveWholeNumber('1250'), 1250)
  assert.equal(parsePositiveWholeNumber(1250.4), 1250)
  assert.equal(parsePositiveWholeNumber(0), null)
  assert.equal(parsePositiveWholeNumber(-5), null)
  assert.equal(parsePositiveWholeNumber('nope'), null)
})

test('normalizeWholeNumberField keeps empty values empty and rounds whole-number inputs', () => {
  assert.equal(normalizeWholeNumberField(''), '')
  assert.equal(normalizeWholeNumberField(null), '')
  assert.equal(normalizeWholeNumberField('42.4'), '42')
  assert.equal(normalizeWholeNumberField('42.6'), '43')
})

test('calculateHorizonReserve and distributable amounts use the gross pot', () => {
  assert.equal(calculateHorizonReserve(100), 10)
  assert.equal(calculateDistributableAmount(100), 90)
  assert.equal(calculateHorizonReserve(105), 11)
  assert.equal(calculateDistributableAmount(105), 94)
})

test('buildEvenSplitAmounts splits only the post-reserve remainder and never goes negative', () => {
  assert.deepEqual(buildEvenSplitAmounts(100, 3), [30, 30, 30])
  assert.deepEqual(buildEvenSplitAmounts(101, 4), [23, 23, 23, 22])

  const smallPotSplit = buildEvenSplitAmounts(5, 10)

  assert.equal(smallPotSplit.length, 10)
  assert.equal(smallPotSplit.reduce((total, amount) => total + amount, 0), calculateDistributableAmount(5))
  assert.ok(smallPotSplit.every(amount => amount >= 0))
})

test('sumPositiveNonOrganizationAmounts ignores Horizon reserve rows and invalid values', () => {
  assert.equal(sumPositiveNonOrganizationAmounts([
    { recipient_type: 'member', amount: '75' },
    { recipient_type: 'squadron', amount: 25 },
    { recipient_type: 'organization', amount: 10 },
    { recipient_type: 'member', amount: -5 },
    { recipient_type: 'member', amount: 'bad' },
  ]), 100)
})

test('calculateReserveBaseAmount prefers the preset total and otherwise derives the base from payout rows', () => {
  const rows = [
    { recipient_type: 'member', amount: 45 },
    { recipient_type: 'squadron', amount: 55 },
    { recipient_type: 'organization', amount: 10 },
  ]

  assert.equal(calculateReserveBaseAmount(100, rows), 100)
  assert.equal(calculateReserveBaseAmount('', rows), 100)
  assert.equal(calculateHorizonReserve(calculateReserveBaseAmount('', rows)), 10)
  assert.equal(calculateReserveBaseAmount('', [{ recipient_type: 'organization', amount: 10 }]), null)
})
