/**
 * 设备录入相关共享类型
 */

/** 质检摘要字段（由质检模板按 summary_visible 配置筛出，最多 5 个） */
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
    model_input_mode?: boolean
    // 质检模板（按型号触发）
    check_template_id?: number
    check_template_name?: string
    summary_loading?: boolean
    summary_fields?: CheckSummaryField[]
    summary_values?: Record<string, any>
    // 联网查询/本地读取补充字段
    color?: string
    capacity?: string
    system_version?: string
    warranty_info?: string
    battery_health?: string
}
