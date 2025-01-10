export async function fetchApiContent(endpoint: string): Promise<Response> {
  return await fetch(endpoint, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
  })
}
