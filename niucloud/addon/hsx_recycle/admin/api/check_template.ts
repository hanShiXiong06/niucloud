import request from '@/utils/request'

export function getCheckTemplatePages(params: Record<string, any>) {
    return request.get('recycle/check_template/pages', { params })
}

export function getCheckTemplateAll(params: Record<string, any> = {}) {
    return request.get('recycle/check_template/all', { params })
}

export function getCheckTemplateSchema(params: Record<string, any> = {}) {
    return request.get('recycle/check_template/schema', { params })
}

export function initDefaultCheckTemplate() {
    return request.post('recycle/check_template/init_default', {}, { showSuccessMessage: true })
}

export function addCheckTemplate(params: Record<string, any>) {
    return request.post('recycle/check_template', params, { showSuccessMessage: true })
}

export function editCheckTemplate(id: number, params: Record<string, any>) {
    return request.put(`recycle/check_template/${id}`, params, { showSuccessMessage: true })
}

export function deleteCheckTemplate(id: number) {
    return request.delete(`recycle/check_template/${id}`, { showSuccessMessage: true })
}

export function setDefaultCheckTemplate(id: number) {
    return request.put(`recycle/check_template/${id}/default`, {}, { showSuccessMessage: true })
}

export function getCheckGroups(params: Record<string, any>) {
    return request.get('recycle/check_template_group', { params })
}

export function saveCheckGroup(params: Record<string, any>) {
    return request.post('recycle/check_template_group', params, { showSuccessMessage: true })
}

export function deleteCheckGroup(id: number) {
    return request.delete(`recycle/check_template_group/${id}`, { showSuccessMessage: true })
}

export function getCheckFields(params: Record<string, any>) {
    return request.get('recycle/check_template_field', { params })
}

export function saveCheckField(params: Record<string, any>, showSuccessMessage = true) {
    return request.post('recycle/check_template_field', params, { showSuccessMessage })
}

export function deleteCheckField(id: number) {
    return request.delete(`recycle/check_template_field/${id}`, { showSuccessMessage: true })
}

export function saveCheckOption(params: Record<string, any>) {
    return request.post('recycle/check_template_option', params, { showSuccessMessage: true })
}

export function deleteCheckOption(id: number) {
    return request.delete(`recycle/check_template_option/${id}`, { showSuccessMessage: true })
}

export function setCheckOptionDefault(id: number, isDefault: number) {
    return request.post(`recycle/check_template_option/${id}/default`, { is_default: isDefault }, { showSuccessMessage: true })
}
