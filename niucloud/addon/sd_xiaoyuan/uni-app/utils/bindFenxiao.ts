import { bindInvite } from '../api/xiaoyuan'

let bindCalled = false

/**
 * 绑定分销关系（校园帮插件）
 * 从缓存中获取pid，如果已登录且有pid则调用绑定接口
 * 未登录或无pid时静默返回，不报错
 */
export function tryBindFenxiao() {
    // 防止重复调用
    if (bindCalled) return

    const pid = uni.getStorageSync('pid')
    console.log('pid',pid);
    
    if (!pid) return

    // const token = uni.getStorageSync('token')
    // console.log('token',token);
    
    // if (!token) return

    bindCalled = true

    bindInvite({ pid: pid }).then(() => {
        // 绑定成功后清除标记，允许下次登录后再次尝试
    }).catch(() => {
        // 静默失败，不影响页面
        bindCalled = false
    })
}
