export const getStatusValue = (item: any) => {
    return item?.value ?? item?.status ?? ''
}

export const getStatusLabel = (item: any) => {
    return item?.label || item?.name || item?.title || '-'
}

export const getStatusKey = (item: any, index: number) => {
    return `${ getStatusValue(item) }-${ index }`
}

export const hasStatusCount = (item: any) => {
    return typeof item?.count !== 'undefined'
}

export const isSameStatus = (current: number | string, value: number | string) => {
    return String(current) === String(value)
}
