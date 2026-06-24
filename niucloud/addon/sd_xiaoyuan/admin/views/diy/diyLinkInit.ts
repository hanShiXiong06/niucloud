export const emptyLink = (url = '') => ({ name: '', parent: '', title: '', url })

export const ensureItemLink = (item: any, url = '') => {
    if (!item || typeof item !== 'object') return
    const linkUrl = url || item.url || (item.link && item.link.url) || ''
    if (!item.link || typeof item.link !== 'object') {
        item.link = emptyLink(linkUrl)
    } else {
        if (item.link.title === undefined) item.link.title = ''
        if (item.link.name === undefined) item.link.name = ''
        if (item.link.parent === undefined) item.link.parent = ''
        if (item.link.url === undefined) item.link.url = linkUrl
    }
}

export const ensureListLinks = (list: any) => {
    if (!Array.isArray(list)) return
    list.forEach((item) => ensureItemLink(item))
}

export const ensureFieldLink = (component: any, field: string, fallbackUrl = '') => {
    if (!component) return
    const url = fallbackUrl
        || (typeof component.moreUrl === 'string' ? component.moreUrl : '')
        || (component.moreUrl && component.moreUrl.url ? component.moreUrl.url : '')
        || (typeof component.url === 'string' ? component.url : '')
    if (!component[field] || typeof component[field] !== 'object') {
        component[field] = emptyLink(url)
    } else {
        if (component[field].title === undefined) component[field].title = ''
        if (component[field].name === undefined) component[field].name = ''
        if (component[field].parent === undefined) component[field].parent = ''
        if (component[field].url === undefined) component[field].url = url
    }
}
