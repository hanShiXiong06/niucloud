import { useLogin } from '@/hooks/useLogin'
import { getCampusAuthStatus, getConfig } from '../api/xiaoyuan'

export function isPublishEntryPath(url: string): boolean {
    if (!url || typeof url !== 'string') return false
    const u = url.split('?')[0]
    const parts = [
        '/buy/create',
        '/send/create',
        '/express/pickup',
        '/print/create',
        '/trash/create',
        '/queue/create',
        '/seat/create',
        '/carry/create',
        '/clean/create',
        '/help/create',
        '/game/publish',
        '/parttime/create',
        '/companion/create',
        '/community/publish',
        '/confession/publish',
        '/secondhand/publish',
        '/lost_found/publish',
        '/group/create',
        '/house/publish',
    ]
    return parts.some((p) => u.includes(p))
}

export async function ensureCanPublish(loginBackUrl?: string): Promise<boolean> {
    const memberInfo = uni.getStorageSync('wap_member_info')
    if (!memberInfo?.member_id) {
        useLogin().setLoginBack({ url: loginBackUrl || '/addon/sd_xiaoyuan/pages/index/index' })
        return false
    }

    let cfg: any = uni.getStorageSync('xiaoyuan_config')
    if (!cfg || cfg.require_auth_publish === undefined) {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            cfg = res.data
            uni.setStorageSync('xiaoyuan_config', res.data)
        }
    }
    if (cfg && Number(cfg.require_auth_publish) === 0) {
        return true
    }

    const res: any = await getCampusAuthStatus()
    if (res.code === 1) {
        const status = Number(res.data?.status)
        if (status === 2) return true

        const messageMap: Record<number, string> = {
            1: '您的实名认证正在审核中，请审核通过后再发布',
            [-1]: `实名认证审核未通过${res.data?.refuse_reason ? `：${res.data.refuse_reason}` : ''}`,
            0: '发布前需先完成校园实名认证',
        } as any
        const content = messageMap[status] || '发布前需先完成校园实名认证'

        uni.showModal({
            title: '实名认证提示',
            content,
            confirmText: '去认证',
            success: (modalRes) => {
                if (modalRes.confirm) {
                    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/campus/auth' })
                }
            },
        })
        return false
    }

    uni.showToast({ title: '认证状态检查失败，请稍后重试', icon: 'none' })
    return false
}
