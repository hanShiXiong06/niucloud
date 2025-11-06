import { computed } from 'vue'
import type { QuotationDataItem } from './useQuotationData'

/**
 * 表格行类型（处理后）
 */
export interface ProcessedTableRow extends QuotationDataItem {
    showModel: boolean
    modelRowspan: number
    showRemark: boolean
    remarkRowspan: number
}

/**
 * 分组表格类型
 */
export interface GroupedTable {
    id: number
    configColumns: string[]
    rows: ProcessedTableRow[]
    modelCount: number
}

/**
 * 配置项排序优先级
 */
const CONFIG_SORT_ORDER = [
    // 第一组：全套充新系列
    ['全套充新    橙色', '全套充新    白色', '全套充新    蓝色'],
    // 第二组：靓机-单机系列
    ['靓机-单机100🔋在保100+', '高保靓充50次内在保280+', '靓机-单机 95电池＋在保60+', '小花电池95+保修无要求'],
    // 第三组：保靓充系列
    ['高保靓充100次内在保250+', '中保靓充100🔋在保100+', '靓机', '小花'],
    // 第四组：靓机/小花
    ['小花', '靓机'],
    // 第五组：花机/内爆
    ['花机', '内爆可测']
].flat()

/**
 * 配置项排序函数
 */
function sortConfigItems(configItems: string[]): string[] {
    return configItems.sort((a, b) => {
        const indexA = CONFIG_SORT_ORDER.indexOf(a)
        const indexB = CONFIG_SORT_ORDER.indexOf(b)

        // 两者都在排序列表中
        if (indexA !== -1 && indexB !== -1) {
            return indexA - indexB
        }

        // 只有a在排序列表中，a优先
        if (indexA !== -1) return -1

        // 只有b在排序列表中，b优先
        if (indexB !== -1) return 1

        // 都不在排序列表中，按字母排序
        return a.localeCompare(b, 'zh-CN')
    })
}

/**
 * 表格逻辑层 - 处理分组、排序、合并等逻辑
 */
export function useQuotationTable(tableData: () => QuotationDataItem[]) {
    /**
     * 按配置项组合分组的表格数据
     */
    const groupedTables = computed<GroupedTable[]>(() => {
        const data = tableData()
        if (data.length === 0) return []

        // 1. 按配置项组合分组
        const configGroupMap = new Map<string, QuotationDataItem[]>()

        data.forEach(row => {
            // 确保 prices 是对象格式
            let prices = row.prices || {}
            if (Array.isArray(prices)) {
                prices = {}
            }

            // 只有当 prices 是对象且有键时才处理
            if (prices && typeof prices === 'object' && Object.keys(prices).length > 0) {
                // 获取该行的配置项列表并按自定义顺序排序
                const configKeys = sortConfigItems(Object.keys(prices))
                const configKey = configKeys.join('|||')

                if (!configGroupMap.has(configKey)) {
                    configGroupMap.set(configKey, [])
                }
                configGroupMap.get(configKey)!.push(row)
            } else {
                // 空配置项的行单独分组
                const emptyKey = '__empty__'
                if (!configGroupMap.has(emptyKey)) {
                    configGroupMap.set(emptyKey, [])
                }
                configGroupMap.get(emptyKey)!.push(row)
            }
        })

        // 2. 为每个分组生成表格数据
        const tables: GroupedTable[] = []
        let tableIndex = 0

        configGroupMap.forEach((rows, configKey) => {
            tableIndex++
            const configColumns = configKey === '__empty__' ? [] : configKey.split('|||')

            // 处理跨行合并（型号和备注）
            const processedRows = processRowMerging(rows, configColumns)

            tables.push({
                id: tableIndex,
                configColumns,
                rows: processedRows,
                modelCount: new Set(rows.map(r => r.goods_name)).size
            })
        })

        return tables
    })

    /**
     * 处理行合并（型号和备注的跨行合并）
     */
    function processRowMerging(rows: QuotationDataItem[], configColumns: string[]): ProcessedTableRow[] {
        const processedRows: ProcessedTableRow[] = []
        let currentModel = ''
        let modelStartIndex = 0
        let currentRemark = ''
        let remarkStartIndex = 0

        rows.forEach((row, index) => {
            const processedRow: ProcessedTableRow = {
                ...row,
                showModel: false,
                modelRowspan: 0,
                showRemark: false,
                remarkRowspan: 0
            }

            // 处理型号跨行
            if (row.goods_name !== currentModel) {
                // 计算上一个型号的跨行数
                if (modelStartIndex < index) {
                    for (let i = modelStartIndex; i < index; i++) {
                        processedRows[i].modelRowspan = index - modelStartIndex
                    }
                }

                currentModel = row.goods_name
                modelStartIndex = index
                processedRow.showModel = true
                processedRow.modelRowspan = 1
            } else {
                processedRow.showModel = false
                processedRow.modelRowspan = 0
            }

            // 处理备注跨行
            const remark = row.value_info || ''
            if (remark !== currentRemark) {
                // 计算上一个备注的跨行数
                if (remarkStartIndex < index) {
                    for (let i = remarkStartIndex; i < index; i++) {
                        processedRows[i].remarkRowspan = index - remarkStartIndex
                    }
                }

                currentRemark = remark
                remarkStartIndex = index
                processedRow.showRemark = true
                processedRow.remarkRowspan = 1
            } else {
                processedRow.showRemark = false
                processedRow.remarkRowspan = 0
            }

            processedRows.push(processedRow)
        })

        // 处理最后一个型号的跨行
        if (modelStartIndex < processedRows.length) {
            for (let i = modelStartIndex; i < processedRows.length; i++) {
                processedRows[i].modelRowspan = processedRows.length - modelStartIndex
            }
        }

        // 处理最后一个备注的跨行
        if (remarkStartIndex < processedRows.length) {
            for (let i = remarkStartIndex; i < processedRows.length; i++) {
                processedRows[i].remarkRowspan = processedRows.length - remarkStartIndex
            }
        }

        return processedRows
    }

    return {
        groupedTables
    }
}

