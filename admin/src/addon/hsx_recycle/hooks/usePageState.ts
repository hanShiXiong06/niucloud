import { ref, watch, onMounted } from 'vue'

/**
 * 页面状态管理Hook
 * 用于保存和恢复页面状态，如表格展开状态、分页信息等
 * @param pageKey 页面唯一标识
 * @returns 状态管理方法和响应式数据
 */
export function usePageState(pageKey: string) {
  // 表格展开行的状态
  const expandedRows = ref<any[]>([])
  
  // 分页信息
  const pagination = ref({
    page: 1,
    limit: 15,
    total: 0
  })

  // 本地存储键名
  const STORAGE_KEY = `page_state_${pageKey}`

  // 保存状态到localStorage
  const saveState = () => {
    // 确保expandedRows中存储的是ID
    const expandedIds = expandedRows.value.map(row => {
      if (typeof row === 'object' && row !== null && row.id !== undefined) {
        return row.id
      }
      return row
    })
    
    const state = {
      expandedRows: expandedIds,
      pagination: pagination.value
    }
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state))
  }

  // 从localStorage恢复状态
  const restoreState = () => {
    try {
      const stateStr = localStorage.getItem(STORAGE_KEY)
      if (stateStr) {
        const state = JSON.parse(stateStr)
        
        // 恢复分页信息
        if (state.pagination) {
          pagination.value = {
            ...pagination.value,
            ...state.pagination
          }
        }
        
        // 展开行状态需要在表格数据加载后处理
        if (state.expandedRows && state.expandedRows.length > 0) {
          // 保存ID列表，等数据加载后再处理
          expandedRows.value = state.expandedRows
        }
      }
    } catch (error) {
      console.error('恢复页面状态失败:', error)
      // 出错时清除可能损坏的状态
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  // 设置展开行
  const setExpandedRows = (rows: any[]) => {
    
    // 确保存储的是ID而不是完整的行对象
    expandedRows.value = rows.map(row => {
      if (typeof row === 'object' && row !== null && row.id !== undefined) {
        return row.id
      }
      return row
    })
    
    saveState()
  }

  // 设置分页信息
  const setPagination = (paginationInfo: any) => {
    pagination.value = {
      ...pagination.value,
      ...paginationInfo
    }
    saveState()
  }

  // 根据ID列表和当前数据设置展开行
  const applyExpandedRows = (tableData: any[]) => {
    if (!expandedRows.value.length || !tableData.length) return []
    
    
    // 确保expandedRows中存储的是ID
    const expandedIds = expandedRows.value.map(row => {
      if (typeof row === 'object' && row !== null) {
        return row.id
      }
      return row
    })
    
    
    // 返回应该展开的行对象
    return tableData.filter(row => expandedIds.includes(row.id))
  }

  // 清除保存的状态
  const clearState = () => {
    localStorage.removeItem(STORAGE_KEY)
    expandedRows.value = []
    pagination.value = {
      page: 1,
      limit: 15,
      total: 0
    }
  }

  // 监听分页变化，自动保存
  watch(() => pagination.value, () => {
    // 只保存状态，不触发其他操作
    saveState()
  }, { deep: true })

  // 组件挂载时恢复状态
  onMounted(() => {
    restoreState()
  })

  return {
    expandedRows,
    pagination,
    setExpandedRows,
    setPagination,
    applyExpandedRows,
    clearState,
    saveState,
    restoreState
  }
}
