import request from '@/utils/request'

/**
 * 获取易速产品列表
 */
export function getYisuProductList() {
    return request.get('recycle/yisu_product/lists')
}

/**
 * 获取 Tab + Tree 产品目录
 */
export function getExpressProductCatalog(provider = 'yisu') {
    return request.get('recycle/yisu_product/catalog', { params: { provider } })
}

/**
 * 批量更新易速产品
 */
export function batchUpdateYisuProduct(params: any) {
    return request.post('recycle/yisu_product/batch_update', params)
}

/**
 * 修改易速产品状态
 */
export function modifyYisuProductStatus(params: any) {
    return request.post('recycle/yisu_product/modify_status', params)
}

/**
 * 获取启用的易速产品
 */
export function getEnabledYisuProducts() {
    return request.get('recycle/yisu_product/enabled')
}

/**
 * 异步导入快递产品 Excel
 */
export function importExpressProducts(formData: FormData) {
    return request.post('recycle/yisu_product/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
}

/**
 * 下载快递产品 Excel 导入模板
 */
export function downloadExpressProductTemplate() {
    return request.get('recycle/yisu_product/import_template', {
        responseType: 'blob'
    })
}

export function getExpressProductImportTasks() {
    return request.get('recycle/yisu_product/import_tasks')
}

export function getExpressProductImportTask(taskId: string) {
    return request.get(`recycle/yisu_product/import_tasks/${taskId}`)
}

export function retryExpressProductImportTask(taskId: string) {
    return request.post(`recycle/yisu_product/import_tasks/${taskId}/retry`)
}
