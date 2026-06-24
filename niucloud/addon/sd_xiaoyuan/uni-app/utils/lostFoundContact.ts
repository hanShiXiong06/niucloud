export function resolveLostFoundContact(item: { contact_mobile?: string; contact_wechat?: string }) {
    const mobileRaw = String(item.contact_mobile || '').trim()
    const wechatRaw = String(item.contact_wechat || '').trim()
    const isPhone = /^1\d{10}$/.test(mobileRaw)
    let phone = isPhone ? mobileRaw : ''
    let wechat = wechatRaw
    if (!wechat && mobileRaw && !isPhone) {
        wechat = mobileRaw
    }
    return { phone, wechat }
}

export function showLostFoundContactSheet(item: { contact_mobile?: string; contact_wechat?: string }) {
    const { phone, wechat } = resolveLostFoundContact(item)
    const itemList: string[] = []
    const actions: Array<'phone' | 'wechat'> = []
    if (phone) {
        itemList.push('拨打电话')
        actions.push('phone')
    }
    if (wechat) {
        itemList.push('复制微信')
        actions.push('wechat')
    }
    if (itemList.length === 0) {
        uni.showToast({ title: '暂无联系方式', icon: 'none' })
        return
    }
    uni.showActionSheet({
        itemList,
        success: (res) => {
            const action = actions[res.tapIndex]
            if (action === 'phone') {
                uni.makePhoneCall({ phoneNumber: phone })
                return
            }
            if (action === 'wechat') {
                uni.setClipboardData({
                    data: wechat,
                    success: () => uni.showToast({ title: '微信号已复制', icon: 'success' })
                })
            }
        }
    })
}
