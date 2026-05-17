import request from '@/utils/request'

/**
 * 获取设备列表
 */
export function getDeviceList(params: any) {
    return request.get('recycle/excel/lists', { params })
}

/**
 * 获取品牌列表
 */
export function getBrandList() {
    return request.get('recycle/excel/brands')
}

/**
 * 获取统计信息
 */
export function getStatistics() {
    return request.get('recycle/excel/statistics')
}

/**
 * 更新价格
 */
export function updatePrice(data: any) {
    return request.put('recycle/excel/price', data)
}

/**
 * 批量删除
 */
export function batchDelete(data: any) {
    return request.delete('recycle/excel/batch', data)
}

/**
 * 上传Excel文件
 */
export function uploadExcel(formData: FormData) {
    return request.post('recycle/excel/upload', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
}

/**
 * 解析Excel文件
 */
export function parseExcel(data: any) {
    return request.post('recycle/excel/parse', data)
}

/**
 * 预览导入数据
 */
export function previewImport(data: any) {
    return request.post('recycle/excel/preview', data)
}

/**
 * 执行数据导入
 */
export function importData(data: any) {
    return request.post('recycle/excel/import', data)
}

/**
 * 下载导入模板
 */
export function downloadTemplate() {
    return request.get('recycle/excel/template', {
        responseType: 'blob'
    })
}

/**
 * 获取导入历史
 */
export function getImportHistory(params: any) {
    return request.get('recycle/excel/history', { params })
} 