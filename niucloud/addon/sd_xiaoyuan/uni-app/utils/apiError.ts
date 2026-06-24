export function getApiErrorMsg(err: any, fallback = '网络错误') {
    if (!err) return fallback
    if (typeof err === 'string') return err
    return err.msg || err.data?.msg || err.message || err.errMsg || fallback
}

export function showApiError(err: any, fallback = '网络错误') {
    uni.showToast({ title: getApiErrorMsg(err, fallback), icon: 'none' })
}
