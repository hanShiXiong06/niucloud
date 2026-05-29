import { ref } from 'vue'
import { getPrintSceneManualActions, getPrintScenePlan, printByScene } from '@/addon/hsx_recycle/api/printer'

type BizType = 'device' | 'order' | 'return' | 'consignment'

type PrintAction = {
    scene_key: string
    scene_name?: string
    button_text?: string
    button_position?: string
    visible_device_status?: Array<number | string>
    confirm_required?: number | string
    copies?: number | string
}

type PrintTarget = {
    device_id?: number | string
    order_id?: number | string
    return_order_id?: number | string
    consignment_id?: number | string
    biz_id?: number | string
}

const getDefaultPosition = (bizType: BizType) => {
    const map: Record<BizType, string> = {
        device: 'device_actions',
        order: 'order_actions',
        return: 'return_order_actions',
        consignment: 'consignment_order_actions'
    }
    return map[bizType]
}

export function useRecyclePrintActions(bizType: BizType, options: { position?: string } = {}) {
    const manualPrintActions = ref<PrintAction[]>([])
    const printLoading = ref(false)
    const position = options.position || getDefaultPosition(bizType)

    const loadManualPrintActions = async () => {
        try {
            const res: any = await getPrintSceneManualActions({ biz_type: bizType })
            manualPrintActions.value = res?.code === 1 && Array.isArray(res.data) ? res.data : []
        } catch (error) {
            manualPrintActions.value = []
        }
    }

    const getVisiblePrintActions = (record: Record<string, any> = {}) => {
        const currentRecord = record || {}
        return manualPrintActions.value.filter((action: PrintAction) => {
            if ((action.button_position || position) !== position) return false
            const visibleStatuses = Array.isArray(action.visible_device_status)
                ? action.visible_device_status.map((item) => Number(item))
                : []
            if (!visibleStatuses.length) return true
            return visibleStatuses.includes(Number(currentRecord.status || 0))
        })
    }

    const buildTargetPayload = (target: PrintTarget) => {
        const payload: PrintTarget = {}
        Object.keys(target || {}).forEach((key) => {
            const value = (target as any)[key]
            if (value !== undefined && value !== null && value !== '') {
                ;(payload as any)[key] = value
            }
        })
        return payload
    }

    const formatPrintPlanText = (plan: Record<string, any>, action?: PrintAction) => {
        const sceneName = action?.button_text || plan?.scene?.button_text || plan?.scene?.scene_name || action?.scene_name || '打印'
        const templateName = plan?.template?.template_name || plan?.template_name || '默认模板'
        const printerName = plan?.printer?.printer_name || plan?.printer_name || '默认打印机'
        const copies = Number(plan?.copies || plan?.scene?.copies || action?.copies || 1)
        return `场景：${ sceneName }\n模板：${ templateName }\n打印机：${ printerName }\n份数：${ copies }`
    }

    const confirmPrint = (content: string, title: string) => {
        return new Promise<boolean>((resolve) => {
            uni.showModal({
                title,
                content,
                confirmText: '确认打印',
                cancelText: '取消',
                success: (res) => resolve(Boolean(res.confirm)),
                fail: () => resolve(false)
            })
        })
    }

    const executePrintAction = async (action: PrintAction, target: PrintTarget) => {
        if (!action?.scene_key) {
            uni.showToast({ title: '打印场景不存在', icon: 'none' })
            return false
        }

        const payload = buildTargetPayload(target)
        printLoading.value = true
        uni.showLoading({ title: '检查打印计划...' })

        try {
            const planRes: any = await getPrintScenePlan(action.scene_key, payload)
            const plan = planRes?.data || {}
            if (planRes?.code !== 1 || !plan?.can_print) {
                throw planRes || new Error(plan?.message || '打印计划不可用')
            }

            uni.hideLoading()

            const confirmRequired = Number(action.confirm_required ?? 1) === 1
            if (confirmRequired) {
                const confirmed = await confirmPrint(formatPrintPlanText(plan, action), action.button_text || plan?.scene?.button_text || '打印')
                if (!confirmed) return false
            }

            uni.showLoading({ title: '发送打印任务...' })
            const printRes: any = await printByScene(action.scene_key, payload)
            if (printRes?.code === 1) {
                uni.showToast({ title: printRes?.msg || '打印任务已发送', icon: 'success' })
                return true
            }
            throw printRes || new Error('打印失败')
        } catch (error: any) {
            uni.hideLoading()
            uni.showToast({
                title: error?.msg || error?.message || error?.data?.message || '打印失败',
                icon: 'none'
            })
            return false
        } finally {
            uni.hideLoading()
            printLoading.value = false
        }
    }

    return {
        manualPrintActions,
        printLoading,
        loadManualPrintActions,
        getVisiblePrintActions,
        executePrintAction
    }
}
