export type AnyRecord = Record<string, any>
export type MaybePromise<T> = T | Promise<T>
export type MobileSchemaValue<T extends AnyRecord, V> = V | ((model: T) => V)

export interface MobileOption {
    label: string
    value: string | number | boolean
    disabled?: boolean
    children?: MobileOption[]
    leaf?: boolean
}

export interface MobileFilterToolbarItem extends AnyRecord {
    label: string
    value: string | number
    icon?: string
    count?: number
    active?: boolean
    arrow?: boolean
    disabled?: boolean
    subscribedText?: string
}

export type MobileFilterSelectionMode = 'none' | 'single' | 'multiple'

export type MobileSearchTrigger = 'enter' | 'button' | 'icon' | 'clear' | 'debounce'

export interface MobileSwipeActionOption extends AnyRecord {
    text: string
    name: string | number
    icon?: string
    iconSize?: string | number
    tone?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger'
    disabled?: boolean
    style?: AnyRecord
}

export interface MobileComponentCatalogItem extends AnyRecord {
    key: string
    label: string
    group?: string
    description?: string
    icon?: string
    badge?: string | number
    target?: string
}

export type MobileCascaderNodeValue = string | number

export interface MobileCascaderOption extends AnyRecord {
    label?: string
    value?: MobileCascaderNodeValue
    children?: MobileCascaderOption[]
    leaf?: boolean
    disabled?: boolean
}

export interface MobileCascaderLoadContext {
    level: number
    path: MobileCascaderOption[]
}

export type MobileFormComponent =
    | 'input'
    | 'textarea'
    | 'number'
    | 'select'
    | 'cascader'
    | 'date'
    | 'upload'
    | 'switch'
    | 'radio'
    | 'checkbox'
    | 'slot'

export interface MobileFormOptionsLoadContext<T extends AnyRecord = AnyRecord> {
    model: T
    field: MobileFormField<T>
    dependencies: AnyRecord
}

export interface MobileFormField<T extends AnyRecord = AnyRecord> {
    prop: string
    label: string
    component?: MobileFormComponent
    placeholder?: string
    defaultValue?: any
    options?: MobileSchemaValue<T, MobileOption[]>
    optionsLoader?: (context: MobileFormOptionsLoadContext<T>) => MaybePromise<MobileOption[]>
    optionsDependencies?: string[]
    props?: MobileSchemaValue<T, AnyRecord>
    rules?: AnyRecord | AnyRecord[]
    visible?: MobileSchemaValue<T, boolean>
    disabled?: MobileSchemaValue<T, boolean>
    required?: MobileSchemaValue<T, boolean>
    requiredMessage?: string
    permission?: string | string[]
    slot?: string
    tip?: MobileSchemaValue<T, string>
    change?: (value: any, model: T) => void
}

export interface MobilePageResult<T> {
    list: T[]
    total: number
}

export interface MobilePageParams {
    page: number
    limit: number
    [key: string]: any
}

export type MobilePageRequest<T> = (params: MobilePageParams) => Promise<any>
export type MobilePageAdapter<T> = (response: any) => MobilePageResult<T>

export type MobileUploadStatus = 'ready' | 'uploading' | 'success' | 'failed'

export interface MobileUploadItem extends AnyRecord {
    uid?: string | number
    name?: string
    url: string
    thumb?: string
    type?: 'image' | 'video' | 'file' | string
    size?: number
    status?: MobileUploadStatus
    message?: string
    percentage?: number
}

export interface MobileUploadContext {
    uid: string | number
    index: number
    onProgress: (percentage: number) => void
}

export type MobileUploadResult = string | MobileUploadItem | AnyRecord

export type HsxHapticType = 'none' | 'light' | 'medium' | 'heavy'

export interface HsxModalOptions {
    title?: string
    content: string
    confirmText?: string
    cancelText?: string
    showCancel?: boolean
    editable?: boolean
    placeholderText?: string
    confirmColor?: string
    cancelColor?: string
    haptic?: HsxHapticType
}

export type MobileBlockType = 'title' | 'text' | 'progress' | 'grid' | 'stack' | 'card' | 'slot' | 'custom'

export interface MobileBlockSchema {
    key?: string
    type: MobileBlockType | string
    props?: AnyRecord
    text?: string | number
    class?: string | string[] | AnyRecord
    style?: string | AnyRecord | Array<string | AnyRecord>
    slot?: string
    children?: MobileBlockSchema[]
}

export interface HsxDiyComponentData extends AnyRecord {
    id?: string | number
    componentName: string
    componentType?: string
    componentIsShow?: boolean
    pageStyle?: string | AnyRecord
    margin?: { top?: number, bottom?: number }
}

export interface HsxDiyPageData {
    global: AnyRecord
    value: HsxDiyComponentData[]
}

export interface MobileTimelineItem extends AnyRecord {
    id?: string | number
    title?: string
    actor?: string
    time?: string
    tone?: 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger'
    tag?: string | { label: string, tone?: 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger' }
    content?: string
    details?: Array<{ label: string, value: any }>
}

export type MobileChartMode = 'light' | 'dark'
export type MobileChartType = 'column' | 'bar' | 'line' | 'area' | 'pie' | 'ring' | 'rose' | 'radar' | 'gauge' | 'funnel' | 'scatter' | 'bubble' | 'candle' | 'mix'

export interface MobileChartSeries extends AnyRecord {
    name?: string
    data: any[]
    type?: string
}

export interface MobileChartData extends AnyRecord {
    categories?: Array<string | number>
    series: MobileChartSeries[]
}

export interface MobileChartPreset {
    type: MobileChartType
    chartData: MobileChartData
    opts: AnyRecord
}

export type MobileAdaptiveWidthClass = 'compact' | 'medium' | 'expanded' | 'large' | 'extra-large'
export type MobileAdaptiveHeightClass = 'compact' | 'medium' | 'expanded'

export interface MobileSafeAreaInsets {
    top: number
    right: number
    bottom: number
    left: number
}

export interface MobileAdaptiveSnapshot {
    windowWidth: number
    windowHeight: number
    pixelRatio: number
    safeAreaInsets: MobileSafeAreaInsets
    widthClass: MobileAdaptiveWidthClass
    heightClass: MobileAdaptiveHeightClass
    isLandscape: boolean
}

export type MobileResponsiveValue<T> = T | Partial<Record<MobileAdaptiveWidthClass, T>>

export type MobileActionTone = 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

export interface MobileActionItem<T extends AnyRecord = AnyRecord> {
    key: string | number
    label: string
    icon?: string
    type?: string
    tone?: MobileActionTone
    plain?: boolean
    block?: boolean
    haptic?: HsxHapticType
    visible?: boolean | ((context: T) => boolean)
    disabled?: boolean | ((context: T) => boolean)
    loading?: boolean
    action?: (context: T) => MaybePromise<any>
    props?: AnyRecord
}

export type MobileDetailItemType = 'text' | 'money' | 'tag' | 'image' | 'link' | 'slot'

export interface MobileDetailItem<T extends AnyRecord = AnyRecord> {
    prop: keyof T & string | string
    label: string
    type?: MobileDetailItemType
    span?: number
    emptyText?: string
    copyable?: boolean
    sensitive?: boolean
    mask?: (value: any, row: T) => any
    visible?: boolean | ((row: T) => boolean)
    tone?: 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger' | ((value: any, row: T) => 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger')
    formatter?: (value: any, row: T) => any
    slot?: string
    props?: AnyRecord
}

export interface MobileEntityFieldMap {
    key?: string
    title?: string
    subtitle?: string
    avatar?: string
    description?: string
    status?: string
}

export interface MobileProductFieldMap {
    key?: string
    title?: string
    subtitle?: string
    image?: string
    price?: string
    originalPrice?: string
    status?: string
    description?: string
}
