import type { AnyRecord, DialogMode } from '../types'
import { useDialog } from './useDialog'
import { useTablePage, type UseTablePageOptions } from './useTablePage'

export interface UseCrudPageOptions<T extends AnyRecord, S extends AnyRecord>
    extends UseTablePageOptions<T, S> {
    create?: (data: Partial<T>) => Promise<any>
    update?: (data: Partial<T>) => Promise<any>
    remove?: (row: T) => Promise<any>
    getKey?: (row: T) => string | number | undefined
}

export function useCrudPage<T extends AnyRecord = AnyRecord, S extends AnyRecord = AnyRecord>(
    options: UseCrudPageOptions<T, S>
) {
    const table = useTablePage<T, S>(options)
    const dialog = useDialog<T>()

    const submit = async (data: Partial<T>, mode: DialogMode = dialog.mode.value) => {
        if (mode === 'view') return
        if (mode === 'edit') {
            if (!options.update) throw new Error('未配置 update 方法')
            await options.update(data)
        } else {
            if (!options.create) throw new Error('未配置 create 方法')
            await options.create(data)
        }
        dialog.close()
        await table.reload()
    }

    const remove = async (row: T) => {
        if (!options.remove) throw new Error('未配置 remove 方法')
        await options.remove(row)
        await table.reload(rowsOnLastPage(table.rows.value.length, table.pagination.page))
    }

    return { ...table, dialog, submit, remove }
}

function rowsOnLastPage(rowCount: number, currentPage: number): boolean {
    return rowCount <= 1 && currentPage > 1
}
