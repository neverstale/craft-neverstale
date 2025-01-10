interface RescheduleFlagPayload {
  flagId: string
  endpoint: string
  csrfToken: string
  contentId: string
  expiredAt: string
}

export async function rescheduleFlag(payload: RescheduleFlagPayload): Promise<Response> {
  return await fetch(payload.endpoint, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': payload.csrfToken,
    },
    body: JSON.stringify({
      flagId: payload.flagId,
      contentId: payload.contentId,
      expiredAt: payload.expiredAt,
    }),
  })
}
