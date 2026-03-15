function buildTemplateVisitOptions({ onSuccess, onError, onFinish }) {
  return {
    preserveScroll: true,
    preserveState: true,
    only: ['operationTemplates', 'flash'],
    onSuccess,
    onError,
    onFinish,
  }
}

export function resolveOperationTemplateVisit(visitPage, fallbackTemplateId = null) {
  const templateId = visitPage?.props?.flash?.operationTemplate?.id ?? fallbackTemplateId ?? null
  const template = (visitPage?.props?.operationTemplates ?? []).find(templateItem => {
    return Number(templateItem?.id) === Number(templateId)
  }) ?? null

  return {
    templateId,
    template,
  }
}

export function renameOperationTemplate({ router, route, Ziggy, templateId, name, onSuccess, onError, onFinish }) {
  return router.put(
    route('operations.templates.update', { template: templateId }, Ziggy),
    {
      name,
    },
    buildTemplateVisitOptions({ onSuccess, onError, onFinish })
  )
}

export function updateOperationTemplate({ router, route, Ziggy, templateId, payload, onSuccess, onError, onFinish }) {
  return router.put(
    route('operations.templates.update', { template: templateId }, Ziggy),
    {
      payload,
    },
    buildTemplateVisitOptions({ onSuccess, onError, onFinish })
  )
}

export function createOperationTemplate({ router, route, Ziggy, name, scope, squadronId, payload, onSuccess, onError, onFinish }) {
  return router.post(
    route('operations.templates.store', {}, Ziggy),
    {
      name,
      scope,
      squadron_id: squadronId,
      payload,
    },
    buildTemplateVisitOptions({ onSuccess, onError, onFinish })
  )
}

export function deleteOperationTemplate({ router, route, Ziggy, templateId, onSuccess, onError, onFinish }) {
  return router.delete(
    route('operations.templates.destroy', { template: templateId }, Ziggy),
    buildTemplateVisitOptions({ onSuccess, onError, onFinish })
  )
}
