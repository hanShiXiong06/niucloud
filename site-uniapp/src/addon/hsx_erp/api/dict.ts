/**
 * ERP 前端字典（与后端 addon/hsx_erp/app/dict/ErpDict.php 保持一致）
 */
import request from '@/utils/request'
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'

export type ErpDictOption = {
    value: string
    label: string
    type?: string
    filterable?: boolean
}

export type ErpDictMap = Record<string, ErpDictOption[]>

// 库存状态映射
export const INVENTORY_STATUS_MAP: Record<string, string> = {
    pending_in: '待入库',
    pending_photo: '待拍照',
    inbound_rejected: '入库驳回',
    in_stock: '在库',
    sold: '已售',
    returned: '已退货',
    void: '已作废',
    refurbishing: '整备中',
    pending_pricing: '待销售定价',
    available_for_sale: '在售',
    locked: '销售锁定',
    outbound: '已出库',
    lost: '盘亏丢失'
}

export const ERP_DICT_FALLBACK: ErpDictMap = {
    asset_status: Object.entries(INVENTORY_STATUS_MAP)
        .filter(([value]) => ['in_stock', 'sold', 'returned', 'void', 'lost'].includes(value))
        .map(([value, label]) => ({
            value,
            label,
            type: ({ in_stock: 'success', sold: 'primary', returned: 'warning', void: 'info', lost: 'error' } as Record<string, string>)[value] || 'info',
            filterable: true
        })),
    purchase_finance_status: [
        { value: 'pending', label: '待付款', type: 'warning', filterable: true },
        { value: 'partial', label: '部分付款', type: 'primary', filterable: true },
        { value: 'settled', label: '已结清', type: 'success', filterable: true },
        { value: 'void', label: '已撤销', type: 'info', filterable: true }
    ],
    stocktake_status: [
        { value: 'counting', label: '盘点中', type: 'primary', filterable: true },
        { value: 'pending_review', label: '待复核', type: 'warning', filterable: true },
        { value: 'completed', label: '已完成', type: 'success', filterable: true },
        { value: 'cancelled', label: '已取消', type: 'info', filterable: true }
    ],
    stocktake_result: [
        { value: 'pending', label: '待盘', type: 'info', filterable: true },
        { value: 'normal', label: '正常', type: 'success', filterable: true },
        { value: 'missing', label: '盘亏', type: 'error', filterable: true },
        { value: 'surplus', label: '盘盈', type: 'warning', filterable: true },
        { value: 'location_mismatch', label: '位置不符', type: 'warning', filterable: true },
        { value: 'status_abnormal', label: '状态异常', type: 'error', filterable: true }
    ],
    purchase_order_status: [
        { value: 'completed', label: '已完成', type: 'success', filterable: true },
        { value: 'returned', label: '已退货', type: 'warning', filterable: true },
        { value: 'void', label: '已撤销', type: 'info', filterable: true }
    ],
    purchase_return_flow: [
        { value: 'direct_void', label: '直接退货', type: 'warning', filterable: false },
        { value: 'refund_receivable', label: '退货退款', type: 'warning', filterable: false },
        { value: 'mixed', label: '退货退款', type: 'warning', filterable: false }
    ],
    purchase_refund_mode: [
        { value: 'none', label: '无需退款', type: 'info', filterable: false },
        { value: 'cash', label: '退款待确认', type: 'warning', filterable: false },
        { value: 'offset', label: '往来折抵', type: 'primary', filterable: false }
    ],
    return_status: [
        { value: 'pending', label: '旧单待确认', type: 'warning', filterable: true },
        { value: 'confirmed', label: '已完成', type: 'success', filterable: true },
        { value: 'cancelled', label: '已撤销', type: 'info', filterable: true }
    ],
    refurbish_status: [
        { value: 'none', label: '无需整备', type: 'info', filterable: true },
        { value: 'pending', label: '待整备', type: 'warning', filterable: true },
        { value: 'processing', label: '整备中', type: 'primary', filterable: true },
        { value: 'done', label: '整备完成', type: 'success', filterable: true },
        { value: 'failed', label: '整备异常', type: 'error', filterable: true }
    ],
    sale_target: [
        { value: 'unset', label: '去向未定', type: 'info', filterable: true },
        { value: 'peer', label: '卖同行', type: 'primary', filterable: true },
        { value: 'mall', label: '上商城', type: 'primary', filterable: true }
    ],
    listing_status: [
        { value: 'none', label: '无需上架', type: 'info', filterable: true },
        { value: 'need_photo', label: '待拍照', type: 'warning', filterable: true },
        { value: 'need_price', label: '待商城定价', type: 'warning', filterable: true },
        { value: 'need_material', label: '待完善资料', type: 'warning', filterable: true },
        { value: 'ready', label: '待上架', type: 'primary', filterable: true },
        { value: 'pending_shop', label: '待商城运营完善', type: 'warning', filterable: true },
        { value: 'listed', label: '商城已上架', type: 'success', filterable: true }
    ]
}

let dictCache: ErpDictMap | null = null

export async function loadErpDicts(): Promise<ErpDictMap> {
    if (dictCache) return dictCache
    try {
        const res: any = await request.get('erp/dicts')
        const resolved = { ...ERP_DICT_FALLBACK, ...(res?.data || {}) }
        dictCache = resolved
        return resolved
    } catch (e) {
        dictCache = ERP_DICT_FALLBACK
        return ERP_DICT_FALLBACK
    }
}

export function dictLabel(dicts: ErpDictMap | null | undefined, group: string, value: string): string {
    const rows = [...(ERP_DICT_FALLBACK[group] || []), ...(dicts?.[group] || [])]
    return erpEnumLabel(value, Object.fromEntries(rows.map(item => [item.value, item.label])))
}

export function dictType(dicts: ErpDictMap | null | undefined, group: string, value: string): string {
    return (dicts?.[group] || ERP_DICT_FALLBACK[group] || []).find(item => item.value === value)?.type || 'info'
}

export function dictTabs(dicts: ErpDictMap | null | undefined, group: string, withAll = true) {
    const rows = (dicts?.[group] || ERP_DICT_FALLBACK[group] || []).filter(item => item.filterable !== false)
    const tabs = rows.map(item => {
        const label = dictLabel(dicts, group, item.value)
        return { label, name: label, value: item.value }
    })
    return withAll ? [{ label: '全部', name: '全部', value: '' }, ...tabs] : tabs
}

// 成本调整只允许仍在库的设备；已售设备必须走售后利润调整，避免覆盖历史销售利润。
export const COST_ADJUST_ALLOWED: string[] = [
    'in_stock',
    'available_for_sale'
]

export const isCostAdjustAllowed = (status: any) => COST_ADJUST_ALLOWED.includes(String(status || ''))

// 已出库状态：可订正成本，但不联动应付/应收
export const INVENTORY_OUTBOUND = 'outbound'
