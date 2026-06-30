import request from '@/utils/request'

// ==================== 快递公司 ====================

/**
 * 快递公司分页列表
 */
export function getDeliveryCompanyPageList(params: Record<string, any>) {
    return request.get('recycle/delivery/company', { params })
}

/**
 * 快递公司全量列表（下拉用）
 * @param params 可传 { electronic_sheet_switch: 1 } 只取支持电子面单的公司
 */
export function getDeliveryCompanyList(params: Record<string, any> = {}) {
    return request.get('recycle/delivery/company/list', { params })
}

/**
 * 快递公司详情
 */
export function getDeliveryCompanyInfo(id: number) {
    return request.get(`recycle/delivery/company/${id}`)
}

/**
 * 新增快递公司
 */
export function addDeliveryCompany(params: Record<string, any>) {
    return request.post('recycle/delivery/company', params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 编辑快递公司
 */
export function editDeliveryCompany(params: Record<string, any>) {
    return request.put(`recycle/delivery/company/${params.company_id}`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 删除快递公司
 */
export function deleteDeliveryCompany(id: number) {
    return request.delete(`recycle/delivery/company/${id}`, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 一键导入常用快递公司
 */
export function importDeliveryCompanyPresets() {
    return request.post('recycle/delivery/company/import_presets', {}, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 取某服务商下"已绑定且出面单"的公司（电子面单选公司联动用）
 */
export function getDeliveryProviderCompanies(provider: string) {
    return request.get('recycle/delivery/company/provider_companies', { params: { provider } })
}

// ==================== 电子面单模板 ====================

/** 电子面单模板分页列表 */
export function getExpressSheetPageList(params: Record<string, any>) {
    return request.get('recycle/express_sheet', { params })
}

/** 电子面单模板全量列表（发件下拉用） */
export function getExpressSheetList(params: Record<string, any> = {}) {
    return request.get('recycle/express_sheet/list', { params })
}

/** 电子面单模板详情 */
export function getExpressSheetInfo(id: number) {
    return request.get(`recycle/express_sheet/${id}`)
}

/** 邮费支付方式字典 */
export function getExpressSheetPayType() {
    return request.get('recycle/express_sheet/paytype')
}

/** 新增电子面单模板 */
export function addExpressSheet(params: Record<string, any>) {
    return request.post('recycle/express_sheet', params, { showErrorMessage: true, showSuccessMessage: true })
}

/** 编辑电子面单模板 */
export function editExpressSheet(params: Record<string, any>) {
    return request.put(`recycle/express_sheet/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/** 删除电子面单模板 */
export function deleteExpressSheet(id: number) {
    return request.delete(`recycle/express_sheet/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

/** 设为默认电子面单模板 */
export function setDefaultExpressSheet(id: number) {
    return request.put(`recycle/express_sheet/setDefault/${id}`, {}, { showErrorMessage: true, showSuccessMessage: true })
}
