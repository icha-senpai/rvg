export function wholeNumber(value) {
  const amount = Number(value)

  if (!Number.isFinite(amount)) {
    return null
  }

  return Math.round(amount)
}

export function normalizeWholeNumberField(value) {
  if (value === '' || value === null || typeof value === 'undefined') {
    return ''
  }

  const amount = wholeNumber(value)

  return amount === null ? '' : String(amount)
}

export function parsePositiveWholeNumber(value) {
  const amount = wholeNumber(value)

  return Number.isInteger(amount) && amount > 0 ? amount : null
}

export function calculateHorizonReserve(totalAmount) {
  const amount = parsePositiveWholeNumber(totalAmount)

  if (!amount) {
    return 0
  }

  return Math.round(amount * 0.1)
}

export function calculateDistributableAmount(totalAmount) {
  const amount = parsePositiveWholeNumber(totalAmount)

  if (!amount) {
    return 0
  }

  return Math.max(0, amount - calculateHorizonReserve(amount))
}

export function buildEvenSplitAmounts(totalAmount, recipientCount) {
  if (!Number.isInteger(recipientCount) || recipientCount <= 0) {
    return []
  }

  const distributableAmount = calculateDistributableAmount(totalAmount)

  if (distributableAmount <= 0) {
    return []
  }

  const baseAmount = Math.floor(distributableAmount / recipientCount)
  let remainderAmount = distributableAmount - (baseAmount * recipientCount)

  return Array.from({ length: recipientCount }, () => {
    const payout = baseAmount + (remainderAmount > 0 ? 1 : 0)
    remainderAmount = Math.max(0, remainderAmount - 1)

    return payout
  })
}

export function sumPositiveNonOrganizationAmounts(moneyRows) {
  return (moneyRows ?? []).reduce((total, row) => {
    const amount = wholeNumber(row?.amount ?? 0)

    if (!Number.isFinite(amount) || amount <= 0 || row?.recipient_type === 'organization') {
      return total
    }

    return total + amount
  }, 0)
}

export function calculateReserveBaseAmount(presetAmount, moneyRows) {
  const presetBaseAmount = parsePositiveWholeNumber(presetAmount)

  if (presetBaseAmount) {
    return presetBaseAmount
  }

  const moneyBaseAmount = sumPositiveNonOrganizationAmounts(moneyRows)

  return moneyBaseAmount > 0 ? moneyBaseAmount : null
}
