import request from '@/utils/request'

// 检测目录列表
export function getCheckCatalogList(params: Record<string, any>) {
    return request.get('recycle/check_catalog/lists', { params })
}
// 导入批次记录
export function getCheckCatalogBatches() {
    return request.get('recycle/check_catalog/batches')
}
// 导入检测目录(CSV, multipart)
export function importCheckCatalog(data: FormData) {
    return request.post('recycle/check_catalog/import', data, { headers: { 'Content-Type': 'multipart/form-data' } })
}
// 选项级别列表 + 统计
export function getCheckSeverityList(params: Record<string, any>) {
    return request.get('recycle/check_catalog/severity', { params })
}
// 设置单个选项级别
export function setCheckSeverity(id: number, severity: string) {
    return request.post(`recycle/check_catalog/severity/${id}`, { severity })
}
// 批量设置级别
export function batchSetCheckSeverity(ids: number[], severity: string) {
    return request.post('recycle/check_catalog/severity_batch', { ids, severity })
}
// 按关键字打标级别
export function setCheckSeverityByKeyword(keyword: string, severity: string) {
    return request.post('recycle/check_catalog/severity_keyword', { keyword, severity })
}
