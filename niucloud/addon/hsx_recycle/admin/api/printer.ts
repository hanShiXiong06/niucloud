import request from "@/utils/request";

/**
 * 打印机管理 API
 */

// ==================== 打印机品牌 ====================
/**
 * 获取打印机品牌列表
 */
export function getPrinterBrands() {
  return request.get('/recycle/printer/brand_list', {
        showSuccessMessage: false
    });
}

// ==================== 打印机管理 ====================
/**
 * 获取打印机列表
 * @param params 查询参数（可包含 with_status: boolean 是否查询在线状态）
 */
export function getPrinterList(params: Record<string, any> = {}) {
  return request.get('/recycle/printer/lists', {
        params,
        showSuccessMessage: false
    });
}

/**
 * 批量查询打印机状态
 * @param printer_ids 打印机ID数组
 */
export function batchQueryPrinterStatus(printer_ids: number[]) {
  return request.post('/recycle/printer/batch_status', {
    printer_ids
  }, {
    showErrorMessage: true,
        showSuccessMessage: false
    });
}

/**
 * 获取打印机详情
 * @param printer_id 打印机ID
 */
export function getPrinterInfo(printer_id: number) {
    return request.get(`/recycle/printer/${printer_id}`, {
        showSuccessMessage: false
    });
}

/**
 * 添加打印机
 * @param data 打印机数据
 */
export function addPrinter(data: Record<string, any>) {
  return request.post('/recycle/printer', data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 更新打印机信息
 * @param data 打印机数据（需包含printer_id）
 */
export function updatePrinter(data: Record<string, any>) {
  return request.put(`/recycle/printer/${data.printer_id}`, data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 删除打印机
 * @param printer_id 打印机ID
 */
export function deletePrinter(printer_id: number) {
  return request.delete(`/recycle/printer/${printer_id}`, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 切换打印机状态（激活/停用）
 * @param printer_id 打印机ID
 * @param status 状态值（0或1）
 */
export function togglePrinterStatus(printer_id: number, status: number) {
  return request.post(`/recycle/printer/user/toggle/${printer_id}`, { status }, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 测试打印机
 * @param data 测试数据（包含sn, user_name, user_key, content）
 */
export function testPrinter(data: Record<string, any>) {
  return request.post('/recycle/printer/test', data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 打印标签
 * @param data 打印数据
 */
export function printLabel(data: Record<string, any>) {
  return request.post('/recycle/printer/print_label', data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 打印设备标签
 * @param device_id 设备ID
 */
export function printDeviceLabel(device_id: number, data: Record<string, any> = {}) {
  return request.post(`/recycle/printer/print_device_label/${device_id}`, data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
} 

/**
 * 获取设备标签打印计划
 * @param device_id 设备ID
 * @param params 查询参数
 */
export function getDeviceLabelPrintPlan(device_id: number, params: Record<string, any> = {}) {
  return request.get(`/recycle/printer/print_device_label_plan/${device_id}`, {
    params,
    showErrorMessage: true,
    showSuccessMessage: false
  });
}

/**
 * 查询打印机状态
 * @param printer_id 打印机ID
 */
export function queryPrinterStatus(printer_id: number) {
  return request.get(`/recycle/printer/status/${printer_id}`, {
    showErrorMessage: true,
    showSuccessMessage: false
  });
}

// ==================== 用户打印机绑定 ====================
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
 * @param data 打印机绑定数据
 */
export function bindPrinter(data: Record<string, any>) {
  return request.post('/recycle/printer/bind', data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 解绑打印机
 */
export function unbindPrinter() {
  return request.post('/recycle/printer/unbind', {}, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 打印设备标签
 * @param device_id 设备ID
 */
export function _printDeviceLabel(device_id: number, data: Record<string, any> = {}) {
  return printDeviceLabel(device_id, data);
}

// ==================== 打印场景 ====================
/**
 * 获取打印场景列表
 */
export function getPrintSceneList() {
  return request.get('/recycle/print_scene/lists', {
    showSuccessMessage: false
  });
}

/**
 * 获取打印场景配置选项
 */
export function getPrintSceneOptions() {
  return request.get('/recycle/print_scene/options', {
    showSuccessMessage: false
  });
}

/**
 * 保存打印场景配置
 * @param sceneKey 场景标识
 * @param data 配置数据
 */
export function updatePrintScene(sceneKey: string, data: Record<string, any>) {
  return request.put(`/recycle/print_scene/${sceneKey}`, data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 新增自定义打印场景
 */
export function addPrintScene(data: Record<string, any>) {
  return request.post('/recycle/print_scene', data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 删除自定义打印场景
 */
export function deletePrintScene(sceneKey: string) {
  return request.delete(`/recycle/print_scene/${sceneKey}`, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 修改打印场景状态
 * @param sceneKey 场景标识
 * @param status 状态
 */
export function modifyPrintSceneStatus(sceneKey: string, status: number) {
  return request.post(`/recycle/print_scene/status/${sceneKey}`, { status }, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 获取手动打印动作
 */
export function getPrintSceneManualActions(params: Record<string, any> = {}) {
  return request.get('/recycle/print_scene/manual_actions', {
    params,
    showSuccessMessage: false
  });
}

/**
 * 获取场景打印计划
 */
export function getPrintScenePlan(sceneKey: string, params: Record<string, any> = {}) {
  return request.get(`/recycle/print_scene/${sceneKey}/plan`, {
    params,
    showErrorMessage: true,
    showSuccessMessage: false
  });
}

/**
 * 按场景打印
 */
export function printByScene(sceneKey: string, data: Record<string, any> = {}) {
  return request.post(`/recycle/print_scene/${sceneKey}/print`, data, {
    showErrorMessage: true,
    showSuccessMessage: true
  });
}

/**
 * 获取打印日志
 * @param params 查询参数
 */
export function getPrintLogList(params: Record<string, any> = {}) {
  return request.get('/recycle/print_log/lists', {
    params,
    showSuccessMessage: false
  });
}
