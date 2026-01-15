import request from '@/utils/request'


/***************************************************** 核销 ****************************************************/

/**
 * 获取核销记录
 * @param params
 * @returns
 */
export function getVerifyRecord(params: Record<string, any>) {
    return request.get(`verify/verify/record`, params)
}

/**
 * 获取核销信息
 * @param verifyCode
 * @returns
 */
export function getVerifyDetail(verifyCode: string) {
    return request.get(`verify/verify/${verifyCode}`)
}

/**
 * 获取核销详情
 * @param verifyCode
 * @returns
 */
export function getVerifyDetailInfo(verifyCode: string) {
    return request.get(`verify/detail/${ verifyCode }`)
}

/**
 * 核销
 * @param verifyCode
 * @returns
 */
export function verify(verifyCode: string) {
    return request.post(`verify/verify/${ verifyCode }`,{},{ showSuccessMessage: true})
}
    