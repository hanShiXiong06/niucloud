import request from "@/utils/request";

/**
 * 获取模板列表
 * @param params
 * @returns
 */
export function getTemplateList(params: Record<string, any>) {
  return request.get('/recycle/printer_template/lists', { 
    params,
    showSuccessMessage: false
  });
}

/**
 * 获取模板详情
 * @param id
 */
export function getTemplateInfo(id: number) {
  return request.get(`/recycle/printer_template/${id}`, {
    showSuccessMessage: false
  });
}

/**
 * 添加模板
 * @param data
 */
export function addTemplate(data: Record<string, any>) {
  return request.post("/recycle/printer_template", data, { 
    showErrorMessage: true, 
    showSuccessMessage: true 
  });
}

/**
 * 编辑模板
 * @param id
 * @param data
 */
export function editTemplate(id: number, data: Record<string, any>) {
  return request.put(`/recycle/printer_template/${id}`, data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 删除模板
 * @param id
 */
export function deleteTemplate(id: number) {
  return request.delete(`/recycle/printer_template/${id}`, {
    showSuccessMessage: true
  });
}

/**
 * 修改模板状态
 * @param id
 * @param status
 */
export function modifyTemplateStatus(id: number, status: number) {
  return request.post(`/recycle/printer_template/status/${id}`, { status }, {
    showSuccessMessage: true
  });
}

/**
 * 设置默认模板
 * @param id
 */
export function setDefaultTemplate(id: number) {
  return request.post(`/recycle/printer_template/default/${id}`, {}, {
    showSuccessMessage: true
  });
}

/**
 * 获取模板类型列表
 */
export function getTemplateTypeList() {
  return request.get('/recycle/printer_template/type_list', {
    showSuccessMessage: false
  });
}

/**
 * 根据类型获取默认模板
 * @param templateType
 */
export function getDefaultTemplate(templateType: string) {
  return request.get('/recycle/printer_template/default', { 
    params: { template_type: templateType },
    showSuccessMessage: false
  });
}

/**
 * 预览模板
 * @param id
 */
export function previewTemplate(id: number) {
  return request.get(`/recycle/printer_template/preview/${id}`, {
    showSuccessMessage: false
  });
}

/**
 * 测试打印模板
 * @param id
 * @param data
 */
export function testPrintTemplate(id: number, data: Record<string, any>) {
  return request.post(`/recycle/printer_template/test_print/${id}`, data, {
    showSuccessMessage: true
  });
} 

/**
 * 渲染模板（不包含预览数据）
 * @param templateData
 * @param scale
 */
export function renderTemplate(templateData: Record<string, any>, scale: number = 1.0) {
  return request.post('/recycle/printer_template/render', {
    template_data: templateData,
    scale
  }, {
    showSuccessMessage: false
  });
}

/**
 * 验证模板数据
 * @param templateData
 */
export function validateTemplate(templateData: Record<string, any>) {
  return request.post('/recycle/printer_template/validate', {
    template_data: templateData
  }, {
    showSuccessMessage: false
  });
}

/**
 * 验证XML格式
 * @param xml
 */
export function validateXml(xml: string) {
  return request.post('/recycle/printer_template/validate_xml', {
    xml
  }, {
    showSuccessMessage: false
  });
}

/**
 * 提取模板变量
 * @param templateData
 */
export function extractVariables(templateData: Record<string, any>) {
  return request.post('/recycle/printer_template/extract_variables', {
    template_data: templateData
  }, {
    showSuccessMessage: false
  });
}

/**
 * 获取设备打印数据
 * @param deviceId
 */
export function getDevicePrintData(deviceId: number) {
  return request.get(`/recycle/printer_template/device_print_data/${deviceId}`, {
    showSuccessMessage: false
  });
} 