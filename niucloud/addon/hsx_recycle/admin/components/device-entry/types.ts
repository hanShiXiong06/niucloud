import type { DeviceReadings } from './deviceReadings'

/**
 * 设备录入相关共享类型
 */

/** 质检摘要字段（由质检模板按 summary_visible 配置筛出，最多 10 个） */
export interface CheckSummaryField {
    id?: number
    field_key: string
    field_name: string
    component: string
    selection_mode?: string
    unit?: string
    placeholder?: string
    default_value?: any
    is_required?: number
    options?: Array<{ value: string | number; label: string; name?: string }>
}

/** 设备录入行 */
export interface DeviceEntryRow {
    /** 前端渲染用稳定 key（未保存行无 id 时使用） */
    _k?: number
    id?: number | string
    imei: string
    imei2?: string
    serial_number?: string
    device_readings?: DeviceReadings
    model: string
    initial_price: number
    /** 用户自助提交的串号（确认弹窗展示用，只读） */
    user_sn?: string
    category_id?: string | number
    category_path?: Array<string | number>
    saved?: boolean
    saving?: boolean
    dirty?: boolean
    model_path?: Array<string | number>
    /** 型号库搜索反馈，仅用于前端交互 */
    model_search_keyword?: string
    model_search_empty?: boolean
    /** 本地设备桥返回的型号候选，仅用于自动匹配与人工学习，不提交订单。 */
    local_model_aliases?: string[]
    /** 最近一次自动识别/映射到的叶子型号；人工改选时据此判断是否要纠正映射。 */
    local_model_resolved_category_id?: number
    model_alias_learning?: boolean
    // 质检模板（按型号触发）
    check_template_id?: number
    check_template_name?: string
    check_template_bound?: boolean
    check_template_source_name?: string
    check_template_summary_count?: number
    summary_loading?: boolean
    summary_fields?: CheckSummaryField[]
    summary_values?: Record<string, any>
    summary_default_keys?: string[]
    // 联网查询/本地读取补充字段
    color?: string
    /** 设备桥颜色代码仅备查；不是质检模板选项下标。 */
    color_index?: number
    capacity?: string
    system_version?: string
    warranty_info?: string
    battery_health?: string
    battery_cycle_count?: string | number
    /** 由本地设备读取自动预填、仍需签收员核对的摘要字段。 */
    local_prefilled_keys?: string[]
    /** 签收现场上传、后续买家可见的设备图片，逗号分隔素材地址 */
    check_images_buyer?: string
}
