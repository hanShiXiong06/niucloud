import request from '@/utils/request'

/**
 * 校验企微消息入口，并取得当前站点内的真实业务页面。
 */
export function resolveWecomEntry(ticket: string) {
    return request.get('wecom/entry/resolve', { ticket }, {
        showErrorMessage: false,
        showLoading: false
    })
}
