import type { AnyRecord, HsxTableColumn, MaybePromise, ProFormField } from '../../types'

export type HsxTreeTablePickerKey = string | number

export interface HsxTreeTablePickerTab {
    label: string
    value: HsxTreeTablePickerKey
    disabled?: boolean
}

export interface HsxTreeTablePickerRequestParams extends AnyRecord {
    page: number
    limit: number
    treeKey?: HsxTreeTablePickerKey
    treeNode?: AnyRecord
    tab?: HsxTreeTablePickerKey
}

export interface HsxTreeTablePickerResult<T extends AnyRecord = AnyRecord> {
    list: T[]
    total: number
}

export type HsxTreeTablePickerRequest<T extends AnyRecord = AnyRecord> =
    (params: HsxTreeTablePickerRequestParams) => MaybePromise<any>

export type HsxTreeTablePickerResponseAdapter<T extends AnyRecord = AnyRecord> =
    (response: any) => HsxTreeTablePickerResult<T>

export interface HsxTreeTablePickerExpose<T extends AnyRecord = AnyRecord> {
    open: () => Promise<void>
    close: () => void
    reload: () => Promise<void>
    search: () => Promise<void>
    reset: () => Promise<void>
    confirm: () => Promise<void>
    getSelectedRows: () => T[]
    clearSelection: () => void
}

export type HsxTreeTablePickerColumns<T extends AnyRecord = AnyRecord> = HsxTableColumn<T>[]
export type HsxTreeTablePickerSearchSchema<T extends AnyRecord = AnyRecord> = ProFormField<T>[]
