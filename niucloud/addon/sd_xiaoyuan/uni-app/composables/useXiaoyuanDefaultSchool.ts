import { getConfig, getSchoolList } from '../api/xiaoyuan'
import { ensureCanPublish } from './usePublishAuth'

export async function ensureXiaoyuanDefaultSchool(): Promise<void> {
    const cfgRes: any = await getConfig()
    if (cfgRes.code !== 1 || !cfgRes.data) return
    if (Number(cfgRes.data.single_school) !== 1) return
    const listRes: any = await getSchoolList()
    if (listRes.code !== 1) return
    const raw = listRes.data?.list ?? listRes.data
    const list = Array.isArray(raw) ? raw : []
    if (!list.length) return
    const first = list[0]
    const cur = uni.getStorageSync('current_school')
    const curId = cur && cur.id ? Number(cur.id) : 0
    const ok = curId > 0 && list.some((s: any) => Number(s?.id) === curId)
    if (ok) return
    uni.setStorageSync('current_school', first)
    uni.$emit('xiaoyuan_school_changed', first)
}

export function getXiaoyuanSchoolId(): number {
    const s = uni.getStorageSync('current_school')
    return s?.id ? Number(s.id) : 0
}

export function getXiaoyuanSchoolCampus(): string {
    const s = uni.getStorageSync('current_school')
    return s?.campus || ''
}

export async function ensureXiaoyuanSchoolSelected(): Promise<boolean> {
    await ensureXiaoyuanDefaultSchool()
    const id = getXiaoyuanSchoolId()
    if (id > 0) return true
    uni.showModal({
        title: '提示',
        content: '请先选择学校',
        confirmText: '去选择',
        success: (res) => {
            if (res.confirm) {
                uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/school/select' })
            }
        }
    })
    return false
}

export async function guardPublishPageOnEnter(loginBackUrl?: string): Promise<void> {
    await ensureXiaoyuanDefaultSchool()
    const pages = getCurrentPages()
    const page = pages[pages.length - 1] as any
    let url = loginBackUrl || ''
    if (!url && page?.route) {
        url = page.route.startsWith('/') ? page.route : '/' + page.route
    }
    if (!url) url = '/addon/sd_xiaoyuan/pages/index/index'
    const ok = await ensureCanPublish(url)
    if (ok) return
    setTimeout(() => {
        uni.navigateBack({
            delta: 1,
            fail: () => {
                uni.redirectTo({ url: '/addon/sd_xiaoyuan/pages/index/index' })
            }
        })
    }, 300)
}
