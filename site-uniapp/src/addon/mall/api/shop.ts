import request from '@/utils/request'
/**
 * 设置店铺基础信息
 */
export function setShopBaseInfo(params: Record<string, any>) {
    return request.put(`shop/site/setting/set`, params, { showErrorMessage: true, showSuccessMessage: true })
}