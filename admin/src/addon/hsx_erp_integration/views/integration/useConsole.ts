import { onBeforeUnmount, reactive, ref, shallowRef } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import type { Page, Row } from '../../api/console'

export const business: Record<string, string> = { purchase: '采购入库', sale: '销售出库', payment: '付款', receipt: '收款', return: '退货' }
export const resource: Record<string, string> = { product: '商品型号', warehouse: '仓库', party: '往来单位', staff: '员工', payment_method: '收付款方式' }
export const states: Record<string, string> = { awaiting_confirmation:'待关联原单',waiting_source:'待原单就绪',linked:'已关联厂商任务',blocked:'需补充资料',unverified: '待核对', pending: '待发送', processing: '处理中', succeeded: '已取得回执', failed: '失败', enabled: '已启用', disabled: '已暂停', testing: '测试中' }
export const reasons: Record<string, string> = { mapping_missing: '缺少资料映射', api_error: '接口异常', timeout: '请求超时', validation_error: '校验未通过' }
export const label = (map: Record<string, string>, v: string) => map[v] || v || '—'
export const time = (v: unknown) => Number(v) > 0 ? new Date(Number(v) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
export const message = (e: any) => String(e?.msg || e?.message || '加载失败，请重试')
export const permission = (key: string) => useUserStore().rules.includes('hsx_erp_integration_' + key)
export function useQuery(fetch: (params: Row) => Promise<Page>, initial: Row = {}) {
    const filters = reactive({ ...initial }); let applied = { ...initial }
    const rows = shallowRef<Row[]>([]), total = ref(0), page = ref(1), size = ref(20)
    const loading = ref(false), error = ref(''); let seq = 0
    async function load() {
        const token = ++seq; loading.value = true; error.value = ''
        try {
            const data = await fetch({ ...applied, page: page.value, limit: size.value })
            if (token !== seq) return
            if (!Array.isArray(data?.list) || !Number.isFinite(Number(data.count))) throw new Error('返回的数据格式不正确')
            total.value = Number(data.count)
            const last = Math.max(1, Math.ceil(total.value / size.value))
            if (page.value > last) { page.value = last; await load(); return }
            rows.value = data.list
        } catch (e) { if (token === seq) { rows.value = []; total.value = 0; error.value = message(e) } }
        finally { if (token === seq) loading.value = false }
    }
    function search() { applied = { ...filters }; page.value = 1; return load() }
    function reset() { Object.assign(filters, initial); return search() }
    function paginate(p: number) { page.value = p; return load() }
    onBeforeUnmount(() => { ++seq })
    return { filters, rows, total, page, size, loading, error, load, search, reset, paginate }
}
export function useDetail(fetch: (id: number) => Promise<Row>) {
    const visible = ref(false), loading = ref(false), error = ref(''), data = shallowRef<Row | null>(null), id = ref(0)
    let seq = 0
    async function open(value: number) {
        const token = ++seq; id.value = value; visible.value = true; loading.value = true; error.value = ''; data.value = null
        try { const result = await fetch(value); if (token === seq) data.value = result }
        catch (e) { if (token === seq) error.value = message(e) }
        finally { if (token === seq) loading.value = false }
    }
    onBeforeUnmount(() => { ++seq })
    return reactive({ visible, loading, error, data, id, open })
}
export async function confirmAction(text: string, title: string, button: string) {
    try { await ElMessageBox.confirm(text, title, { confirmButtonText: button, cancelButtonText: '取消', type: 'warning' }); return true }
    catch { return false }
}
export function useAction() {
    const busy = ref(false)
    async function run(fn: () => Promise<void>) {
        if (busy.value) return
        busy.value = true
        try { await fn() } catch (e) { ElMessage.error(message(e)) } finally { busy.value = false }
    }
    return { busy, run }
}
