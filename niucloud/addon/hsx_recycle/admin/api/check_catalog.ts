import request from '@/utils/request'

// 检测目录列表
export function getCheckCatalogList(params: Record<string, any>) {
    return request.get('recycle/check_catalog/lists', { params })
}
// 验机表单 schema(传型号节点 model_dict_id 或 product_id)
export function getCheckCatalogSchema(params: { model_dict_id?: number; product_id?: number }) {
    return request.get('recycle/check_catalog/schema', { params })
}
// 导入批次记录
export function getCheckCatalogBatches() {
    return request.get('recycle/check_catalog/batches')
}
// 上传原始CSV，返回 {batch_id, token, total_rows}
export function uploadCheckCatalog(data: FormData) {
    return request.post('recycle/check_catalog/import_upload', data, { headers: { 'Content-Type': 'multipart/form-data' } })
}
// 处理一片，前端循环调用直到 done
export function importChunkCheckCatalog(params: { batch_id: number; token: string; offset: number; limit?: number }) {
    return request.post('recycle/check_catalog/import_chunk', params)
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
