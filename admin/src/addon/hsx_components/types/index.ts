import type { FormItemRule } from 'element-plus'
import type { Component, VNodeChild } from 'vue'

export type AnyRecord = Record<string, any>
export type MaybePromise<T> = T | Promise<T>
export type SchemaValue<T extends AnyRecord, V> = V | ((model: T) => V)
export type VisibleRule<T extends AnyRecord = AnyRecord> = SchemaValue<T, boolean>

export interface SelectOption {
    label: string
    value: string | number | boolean
    disabled?: boolean
    [key: string]: any
}

export interface ProFormOptionsLoadContext<T extends AnyRecord = AnyRecord> {
    model: T
    field: ProFormField<T>
    dependencies: AnyRecord
}

export type HsxCascaderNodeValue = string | number
export type HsxCascaderValue =
    | HsxCascaderNodeValue
    | HsxCascaderNodeValue[]
    | HsxCascaderNodeValue[][]
    | null

export interface HsxCascaderOption extends AnyRecord {
    label?: string
    value?: HsxCascaderNodeValue
    children?: HsxCascaderOption[]
    leaf?: boolean
    disabled?: boolean
}

export interface HsxCascaderLoadContext {
    level: number
    keyword: string
    path: HsxCascaderOption[]
}

export type HsxCascaderOptionsResult = HsxCascaderOption[] | HsxCascaderOption[][]

export interface HsxColumnSettingItem {
    key: string
    label: string
    visible?: boolean
    fixed?: false | 'left' | 'right'
    required?: boolean
    disabled?: boolean
}

export interface HsxExportColumn<T extends AnyRecord = AnyRecord> {
    prop: keyof T & string | string
    label: string
    formatter?: (row: T, value: any, index: number) => any
}

export interface HsxExportContext<T extends AnyRecord = AnyRecord> {
    data: T[]
    columns: HsxExportColumn<T>[]
    query: AnyRecord
    filename: string
    format: 'csv' | 'json'
}

export type HsxExportResult =
    | void
    | Blob
    | ArrayBuffer
    | string
    | { blob?: Blob | ArrayBuffer, url?: string, filename?: string }

export interface HsxImportContext {
    filename: string
    size: number
    extension: string
}

export interface HsxDateRangeShortcut {
    text: string
    value: [Date, Date] | (() => [Date, Date])
}

export type HsxDateBaseValue = string | number | Date
export type HsxDatePickerValue = HsxDateBaseValue | [HsxDateBaseValue, HsxDateBaseValue] | null

export type HsxUploadStatus = 'ready' | 'uploading' | 'success' | 'fail'

export interface HsxUploadItem extends AnyRecord {
    uid?: string | number
    name: string
    url: string
    status?: HsxUploadStatus
    percentage?: number
    size?: number
    type?: string
}

export interface HsxUploadContext {
    uid: string | number
    name: string
    size: number
    type: string
    onProgress: (percentage: number) => void
}

export type HsxUploadResult = string | HsxUploadItem | AnyRecord

export type FormComponentType =
    | 'input'
    | 'textarea'
    | 'input-number'
    | 'select'
    | 'cascader'
    | 'date'
    | 'datetime'
    | 'date-range'
    | 'datetime-range'
    | 'upload'
    | 'switch'
    | 'radio'
    | 'checkbox'
    | 'slot'

export interface ProFormField<T extends AnyRecord = AnyRecord> {
    prop: string
    label?: string
    component?: FormComponentType | Component
    placeholder?: string
    defaultValue?: any
    span?: number
    labelWidth?: string | number
    props?: SchemaValue<T, AnyRecord>
    options?: SchemaValue<T, SelectOption[]>
    optionsLoader?: (context: ProFormOptionsLoadContext<T>) => MaybePromise<SelectOption[]>
    optionsDependencies?: string[]
    rules?: FormItemRule | FormItemRule[]
    visible?: VisibleRule<T>
    disabled?: VisibleRule<T>
    required?: VisibleRule<T>
    requiredMessage?: string
    permission?: string | string[]
    componentKey?: string
    slot?: string
    tip?: SchemaValue<T, string>
    change?: (value: any, model: T) => void
}

export interface ProTableSearchConfig<T extends AnyRecord = AnyRecord>
    extends Omit<ProFormField<T>, 'prop' | 'label'> {
    prop?: string
    label?: string
}

export interface ProTableColumn<T extends AnyRecord = AnyRecord> {
    prop?: keyof T & string | string
    label?: string
    type?: 'selection' | 'index' | 'expand'
    width?: string | number
    minWidth?: string | number
    fixed?: true | 'left' | 'right'
    align?: 'left' | 'center' | 'right'
    headerAlign?: 'left' | 'center' | 'right'
    sortable?: boolean | 'custom'
    showOverflowTooltip?: boolean
    hideInTable?: boolean
    search?: boolean | ProTableSearchConfig
    slot?: string
    headerSlot?: string
    editable?: boolean
    editComponent?: 'input' | 'input-number' | 'select'
    editProps?: AnyRecord
    options?: SelectOption[]
    formatter?: (row: T, value: any, index: number) => any
    render?: (context: HsxTableCellContext<T>) => VNodeChild
    hideInSetting?: boolean
    columnSetting?: {
        visible?: boolean
        fixed?: false | 'left' | 'right'
        required?: boolean
        disabled?: boolean
    }
    [key: string]: any
}

export interface HsxTableCellContext<T extends AnyRecord = AnyRecord> {
    row: T
    column: ProTableColumn<T>
    value: any
    index: number
}

export type HsxTableColumn<T extends AnyRecord = AnyRecord> = ProTableColumn<T>
export type HsxTableSelectionMode = 'independent' | 'children' | 'cascade' | 'leaf'

export interface HsxTreeOptions {
    idKey?: string
    parentKey?: string
    childrenKey?: string
    rootValues?: any[]
    keepOrphans?: boolean
}

export type HsxTagTone = 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger'

export interface HsxTimelineDetail {
    label: string
    value: any
}

export interface HsxTimelineItem extends AnyRecord {
    id?: string | number
    title?: string
    actor?: string
    time?: string
    tone?: HsxTagTone
    tag?: string | { label: string, tone?: HsxTagTone }
    content?: string
    details?: HsxTimelineDetail[]
}

export interface PageRequest {
    page: number
    limit: number
    [key: string]: any
}

export interface PageResult<T> {
    list: T[]
    total: number
}

export type TableRequest<T> = (params: PageRequest) => Promise<any>
export type TableResponseAdapter<T> = (response: any) => PageResult<T>

export interface DialogModeText {
    create?: string
    edit?: string
    view?: string
}

export type DialogMode = 'create' | 'edit' | 'view'

export type HsxActionTone = 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

export interface HsxActionConfirm {
    title?: string
    message?: string
    confirmText?: string
    cancelText?: string
    type?: 'success' | 'warning' | 'info' | 'error'
}

export interface HsxActionItem<T extends AnyRecord = AnyRecord> {
    key: string | number
    label: string
    icon?: string
    type?: HsxActionTone
    plain?: boolean
    link?: boolean
    round?: boolean
    permission?: string | string[]
    visible?: boolean | ((context: T) => boolean)
    disabled?: boolean | ((context: T) => boolean)
    loading?: boolean
    confirm?: boolean | string | HsxActionConfirm
    action?: (context: T) => MaybePromise<any>
    props?: AnyRecord
}

export type HsxDetailItemType = 'text' | 'money' | 'tag' | 'image' | 'link' | 'slot'

export interface HsxDetailItem<T extends AnyRecord = AnyRecord> {
    prop: keyof T & string | string
    label: string
    type?: HsxDetailItemType
    span?: number
    minWidth?: string | number
    labelWidth?: string | number
    emptyText?: string
    copyable?: boolean
    sensitive?: boolean
    mask?: (value: any, row: T) => any
    visible?: boolean | ((row: T) => boolean)
    permission?: string | string[]
    tone?: HsxTagTone | ((value: any, row: T) => HsxTagTone)
    formatter?: (value: any, row: T) => any
    slot?: string
    props?: AnyRecord
}

export interface HsxEntityFieldMap {
    key?: string
    title?: string
    subtitle?: string
    avatar?: string
    description?: string
    status?: string
}

export interface HsxProductFieldMap {
    key?: string
    title?: string
    subtitle?: string
    image?: string
    price?: string
    originalPrice?: string
    status?: string
    description?: string
}
