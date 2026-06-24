import { getWeappTemplateId } from '@/app/api/system'
import { recordRunnerSubscribe } from '../api/runner'

export function requestRunnerOrderSubscribe(): Promise<number> {
    return new Promise((resolve) => {
        // #ifdef MP-WEIXIN
        getWeappTemplateId('sd_xiaoyuan_order_pay_runner').then(({ data }) => {
            const tmplIds = Array.isArray(data) ? data : []
            if (!tmplIds.length) {
                resolve(0)
                return
            }
            uni.requestSubscribeMessage({
                tmplIds,
                success: (res: any) => {
                    let acceptNum = 0
                    tmplIds.forEach((id: string) => {
                        if (res[id] === 'accept') {
                            acceptNum++
                        }
                    })
                    if (acceptNum > 0) {
                        recordRunnerSubscribe({ num: acceptNum }).then((apiRes: any) => {
                            const left = apiRes?.data?.weapp_subscribe_num
                            resolve(typeof left === 'number' ? left : acceptNum)
                        }).catch(() => resolve(acceptNum))
                        return
                    }
                    resolve(0)
                },
                fail: () => resolve(0)
            })
        }).catch(() => resolve(0))
        // #endif
        // #ifndef MP-WEIXIN
        resolve(0)
        // #endif
    })
}

export function requestUserOrderSubscribe() {
    // #ifdef MP-WEIXIN
    getWeappTemplateId('sd_xiaoyuan_order_accept_user,sd_xiaoyuan_order_cancel_user').then(({ data }) => {
        const tmplIds = Array.isArray(data) ? data : []
        if (!tmplIds.length) return
        uni.requestSubscribeMessage({ tmplIds })
    }).catch()
    // #endif
}
