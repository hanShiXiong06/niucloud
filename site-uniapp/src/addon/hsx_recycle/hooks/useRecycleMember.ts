import { img } from '@/utils/common'

export const getRecycleMemberName = (item: any, fallback = '未知客户') => {
    return item?.member_name
        || item?.member?.nickname
        || item?.member?.username
        || item?.recycleUserAddress?.name
        || item?.sender_name
        || item?.customer_name
        || fallback
}

export const getRecycleMemberMobile = (item: any) => {
    return item?.member_mobile
        || item?.member?.mobile
        || item?.member?.username
        || item?.recycleUserAddress?.mobile
        || item?.sender_mobile
        || item?.customer_phone
        || '-'
}

export const getRecycleMemberAvatar = (item: any) => {
    const headimg = item?.member?.headimg || item?.member?.head_img || item?.member?.avatar || ''
    return headimg ? img(headimg) : ''
}

export const getRecycleMemberInitial = (item: any, fallback = '未知客户') => {
    const name = getRecycleMemberName(item, fallback)
    return name ? String(name).slice(0, 1).toUpperCase() : '?'
}
