async function send(method: string, path: string, data: any = {}) {
    const url = '/api/' + path + (method === 'GET' ? '?' + new URLSearchParams(data) : '')
    const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: method === 'GET' ? undefined : JSON.stringify(data) })
    const body = await response.json()
    if (!response.ok || body.code !== 1) throw body
    return body
}
export default { get: (path: string, data?: any) => send('GET', path, data), post: (path: string, data?: any) => send('POST', path, data) }
