import request from "@/utils/request";

/**
 * 获取回收打印机分页列表
 * @param params
 * @returns
 */
export function getPrinterPageList(params: any) {
    return request.get('/recycle/printer', { 
        params,
        showSuccessMessage: false
    });
}

/**
 * 获取打印机详情
 * @param printer_id
 */
export function getPrinterInfo(printer_id: any) {
    return request.get(`/recycle/printer/${printer_id}`, {
        showSuccessMessage: false
    });
}

/**
 * 添加打印机
 * @param data
 */
export function addPrinter(params: Record<string, any>) {
    return request.post("/recycle/printer", params, { showErrorMessage: true, showSuccessMessage: true });
}

/**
 * 编辑打印机
 * @param data
 */
export function editPrinter(params: Record<string, any>) {
    return request.put(`/recycle/printer/${params.printer_id}`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    });
}

/**
 * 删除打印机
 * @param printer_id
 */
export function deletePrinter(printer_id: any) {
    return request.delete(`/recycle/printer/${printer_id}`, {
        showSuccessMessage: true
    });
}

/**
 * 修改打印机状态
 * @param data
 */
export function modifyPrinterStatus(data: any) {
    return request.post(`/recycle/printer/status/${data.printer_id}`, data, {
        showSuccessMessage: true
    });
}

/**
 * 测试打印
 * @param printer_id
 */
export function testPrint(printer_id: any) {
    return request.post(`/recycle/printer/test/${printer_id}`, {}, {
        showSuccessMessage: true
    });
}

/**
 * 刷新打印机令牌
 * @param printer_id
 */
export function refreshPrinterToken(printer_id: any) {
     return request.post(`/recycle/printer/test/${printer_id}`, {}, {
        showSuccessMessage: true
    });
}

/**
 * 获取打印机品牌
 */
export function getPrinterBrand() {
    return request.get('/recycle/printer/brand_list', {
        showSuccessMessage: false
    });
}

/**
 * 获取打印机类型
 */
export function getPrinterType() {
    return request.get('/recycle/printer_template/type_list', {
        showSuccessMessage: false
    });
}

/**
 * 获取打印模板列表
 */
export function getPrinterTemplateList() {
    return request.get('/recycle/printer_template/list', {
        showSuccessMessage: false
    });
}

/**
 * 使用打印模板打印
 * @param data
 */
export function printWithTemplate(data: any) {
    return request.post('/recycle/printer_template/print', data, {
        showSuccessMessage: true
    });
}

/**
 * 获取回收打印机列表
 * @param params
 * @returns
 */
export function getPrinterList(params: Record<string, any>) {
  return request.get(`/recycle/printer/list`, { params });
}

/**
 * 获取打印模板类型列表
 * @returns
 */
export function getPrinterTemplateTypeList() {
  return request.get(`/recycle/printer_template/type_list`);
}

/**
 * 获取打印模板分页列表
 * @param params
 * @returns
 */
export function getPrinterTemplatePageList(params: Record<string, any>) {
  return request.get(`/recycle/printer_template`, { params });
}

/**
 * 获取打印模板详情
 * @param template_id 打印模板ID
 * @returns
 */
export function getPrinterTemplateInfo(template_id: number) {
  return request.get(`/recycle/printer_template/${template_id}`);
}

/**
 * 添加打印模板
 * @param params
 * @returns
 */
export function addPrinterTemplate(params: Record<string, any>) {
  return request.post("/recycle/printer_template", params, { 
    showErrorMessage: true, 
    showSuccessMessage: true 
  });
}

/**
 * 编辑打印模板
 * @param params
 * @returns
 */
export function editPrinterTemplate(params: Record<string, any>) {
  return request.put(`/recycle/printer_template/${params.template_id}`, params, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 删除打印模板
 * @param template_id
 * @returns
 */
export function deletePrinterTemplate(template_id: number) {
  return request.delete(`/recycle/printer_template/${template_id}`, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 修改打印模板状态
 * @param params
 * @returns
 */
export function modifyPrinterTemplateStatus(params: Record<string, any>) {
  return request.post(`/recycle/printer_template/status/${params.template_id}`, params, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 设置默认模板
 * @param template_id
 * @returns
 */
export function setDefaultPrinterTemplate(template_id: number) {
  return request.post(`/recycle/printer_template/default/${template_id}`, {}, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 获取默认模板
 * @param template_type
 * @returns
 */
export function getDefaultPrinterTemplate(template_type: string) {
  return request.get(`/recycle/printer_template/default`, { 
    params: { template_type }
  });
}

/**
 * 创建示例模板
 * @param template_type
 * @returns
 */
export function createExampleTemplate(template_type: string) {
  return request.post(`/recycle/printer_template/create_example`, { 
    template_type 
  }, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 打印标签内容
 * @param params
 * @returns
 */
export function printLabel(params: Record<string, any>) {
  return request.post("/recycle/printer/print_label", params, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 打印设备标签
 * @param device_id 设备ID
 * @returns
 */
export function printDeviceLabel(device_id: string | number) {
  return request.post(`/recycle/printer/print_device_label/${device_id}`);
}

/**
 * 应急打印标签
 * @param data
 * @returns 
 */
export function emergencyPrint(data: Record<string, any>) {
  return request.post("/recycle/printer/emergency_print", data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
} 

/**
 * 打印设备标签
 * @param device_id 设备ID
 * @returns
 */
export function _printDeviceLabel(device_id: string | number) {
  return request.post(`/recycle/printer/print_device_label/${device_id}`);
}

/**
 * 获取打印机品牌列表
 */
export function getPrinterBrands() {
  return request.get('/recycle/printer/brand_list', {
    showSuccessMessage: false
  });
}

/**
 * 获取当前用户绑定的打印机
 */
export function getUserPrinter() {
  return request.get('/recycle/printer/user', {
    showSuccessMessage: false
  });
}

/**
 * 绑定打印机
 * @param data
 */
export function bindPrinter(data: any) {
  return request.post('/recycle/printer/bind', data, {
    showSuccessMessage: true
  });
}

/**
 * 解绑打印机
 */
export function unbindPrinter() {
  return request.post('/recycle/printer/unbind', {}, {
    showSuccessMessage: true
  });
}

/**
 * 测试打印机
 * @param data
 */
export function testPrinter(data: any) {
  return request.post('/recycle/printer/test', data, {
    showSuccessMessage: true
  });
}

/**
 * 获取当前用户的所有打印机列表（包括历史添加的）
 */
export function getUserPrinterList() {
  return request.get('/recycle/printer/lists', {
    showSuccessMessage: false
  });
}

/**
 * 更新打印机信息
 * @param data
 */
export function updateUserPrinter(data: any) {
  return request.put(`/recycle/printer/${data.printer_id}`, data, {
    showSuccessMessage: true
  });
}

/**
 * 删除用户打印机
 * @param printer_id
 */
export function deleteUserPrinter(printer_id: any) {
  return request.delete(`/recycle/printer/${printer_id}`, {
    showSuccessMessage: true
  });
}

/**
 * 切换打印机状态（激活/停用）
 * @param printer_id
 * @param status
 */
export function togglePrinterStatus(printer_id: any, status: number) {
  return request.post(`/recycle/printer/user/toggle/${printer_id}`, { status }, {
    showSuccessMessage: true
  });
}