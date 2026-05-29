export const formatMoney = (value: any) => {
    return Number(value || 0).toFixed(2)
}

export const formatTime = (value: any) => {
    if (value === null || value === undefined || value === '') return '-'
    if (typeof value === 'string' && value.includes('-')) return value

    const numeric = Number(value)
    if (Number.isNaN(numeric) || numeric <= 0) return String(value)

    const timestamp = numeric > 9999999999 ? numeric : numeric * 1000
    const date = new Date(timestamp)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hour = String(date.getHours()).padStart(2, '0')
    const minute = String(date.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hour}:${minute}`
}

export const joinAddress = (item: Record<string, any>, prefix: 'sender' | 'receiver') => {
    const keys = [
        `${prefix}_province`,
        `${prefix}_city`,
        `${prefix}_district`,
        `${prefix}_address`
    ]
    return keys.map(key => item[key]).filter(Boolean).join('')
}

export const getStatusTextClass = (type: string) => {
    const map: Record<string, string> = {
        success: 'text-[#18a058]',
        warning: 'text-[#f0a020]',
        danger: 'text-[#d03050]',
        info: 'text-[#909399]',
        primary: 'text-[#2979ff]'
    }

    return map[type] || map.primary
}

/**
 * 拨打电话
 * @param phone 手机号
 */
export const makePhoneCall = (phone: string) => {
    if (!phone || phone === '-') return
    uni.makePhoneCall({ phoneNumber: phone })
}
