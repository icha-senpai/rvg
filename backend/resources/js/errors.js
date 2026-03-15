function unwrapFirstError(value) {
  if (Array.isArray(value)) {
    return unwrapFirstError(value[0])
  }

  if (value && typeof value === 'object') {
    const firstKey = Object.keys(value)[0]
    return firstKey ? unwrapFirstError(value[firstKey]) : null
  }

  if (value === null || value === undefined) {
    return null
  }

  const message = String(value).trim()
  return message || null
}

export function extractFirstErrorMessage(errors, fallbackMessage = 'Please check the form and try again.') {
  return unwrapFirstError(errors) ?? fallbackMessage
}

export function notifyError({ title = 'Error', message = 'Something went wrong.' } = {}) {
  try {
    const normalizedMessage = String(message ?? '').trim()
    if (!normalizedMessage) return

    const now = Date.now()
    const last = window.__hz_lastErrorDialog
    const sameMessage = last?.message && last.message === normalizedMessage

    if (sameMessage && last?.at && now - last.at < 1500) {
      return
    }

    window.__hz_lastErrorDialog = { at: now, message: normalizedMessage }

    window.dispatchEvent(
      new CustomEvent('hz:error', {
        detail: {
          title,
          message: normalizedMessage,
        },
      })
    )
  } catch (_error) {
  }
}

export function notifyErrorFromErrors(errors, fallbackMessage = 'Please check the form and try again.', title = 'Error') {
  const message = extractFirstErrorMessage(errors, fallbackMessage)
  notifyError({ title, message })
  return message
}
