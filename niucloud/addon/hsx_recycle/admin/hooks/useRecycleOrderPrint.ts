import { ref, readonly } from 'vue'
import { getRecycleOrderPrintInfo } from '../api/recycle_order'
import { buildRecycleOrderPrintHtml, prepareRecycleOrderPrint } from '../utils/recycleOrderPrint'

/** 先同步打开打印页，再异步取完整订单，避免弹窗拦截和列表筛选造成漏打设备。 */
export function useRecycleOrderPrint(options: { onError: (message: string) => void }) {
  const printingOrderId = ref<number | null>(null)
  const printOrder = async (row: { id: number }) => {
    if (printingOrderId.value !== null) return
    const target = window.open('', '_blank', 'width=980,height=900')
    if (!target) {
      options.onError('打印窗口被浏览器拦截，请允许本站弹窗后重试')
      return
    }
    target.opener = null
    target.document.title = '正在准备回收单'
    const loading = target.document.createElement('p')
    loading.textContent = '正在加载完整回收单，请稍候…'
    loading.style.cssText = 'padding:32px;font:16px sans-serif'
    target.document.body.appendChild(loading)
    printingOrderId.value = row.id
    try {
      const response = await getRecycleOrderPrintInfo(row.id)
      if (!response.data?.id || !Array.isArray(response.data.devices)) throw new Error('未获取到完整回收单，请刷新后重试')
      if (target.closed) return
      loading.textContent = '正在生成设备二维码，请稍候…'
      const printData = await prepareRecycleOrderPrint(response.data)
      if (target.closed) return
      target.document.open()
      target.document.write(buildRecycleOrderPrintHtml(printData))
      target.document.close()
      const print = () => { if (!target.closed) { target.focus(); target.print() } }
      target.document.getElementById('print-order')?.addEventListener('click', print)
      // 等字体及排版就绪再调浏览器原生打印；取消打印不影响任何业务状态。
      await target.document.fonts?.ready
      await Promise.all(Array.from(target.document.images).map(img => img.decode()))
      if (!target.closed) target.setTimeout(print, 200)
    } catch (error: any) {
      if (!target.closed) target.close()
      options.onError(error?.msg || error?.message || '回收单加载失败，请重试')
    } finally {
      printingOrderId.value = null
    }
  }
  return { printOrder, printingOrderId: readonly(printingOrderId) }
}
