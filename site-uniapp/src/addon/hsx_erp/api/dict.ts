/**
 * ERP 前端字典（与后端 addon/hsx_erp/app/dict/ErpDict.php 保持一致）
 */

// 库存状态映射
export const INVENTORY_STATUS_MAP: Record<string, string> = {
    pending_in: '待入库',
    pending_photo: '待拍照',
    inbound_rejected: '入库驳回',
    in_stock: '在库',
    refurbishing: '整备中',
    pending_pricing: '待销售定价',
    available_for_sale: '在售',
    locked: '销售锁定',
    outbound: '已出库',
    lost: '盘亏丢失'
}

// 不允许调整成本的状态（与后端 adjustCost 的 blocked 一致）
export const COST_ADJUST_BLOCKED: string[] = [
    'lost',
    'pending_in',
    'inbound_rejected'
]

// 已出库状态：可订正成本，但不联动应付/应收
export const INVENTORY_OUTBOUND = 'outbound'
