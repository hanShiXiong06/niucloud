import request from "@/utils/request";

export function getRecycleDeviceModelDictList(params: Record<string, any>) {
  return request.get("/recycle/recycle_device_model_dict", { params });
}

export function getRecycleDeviceModelDictOptions(params: Record<string, any> = {}) {
  return request.get("/recycle/recycle_device_model_dict/options", { params });
}

export function getRecycleDeviceModelDictTree(params: Record<string, any> = {}) {
  return request.get("/recycle/recycle_device_model_dict/tree", { params });
}

export function getRecycleDeviceModelDictChildren(params: Record<string, any> = {}) {
  return request.get("/recycle/recycle_device_model_dict/children", { params });
}

export function resolveRecycleDeviceModelAlias(aliases: string[]) {
  return request.post("/recycle/recycle_device_model_dict/alias/resolve", { aliases });
}

export function bindRecycleDeviceModelAlias(data: { aliases: string[]; category_id: number | string }) {
  return request.post("/recycle/recycle_device_model_dict/alias/bind", data, {
    showErrorMessage: true,
  });
}

export function addRecycleDeviceModelDict(data: Record<string, any>) {
  return request.post("/recycle/recycle_device_model_dict", data, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

export function ensureRecycleDeviceModelDictChild(data: { parent_id: number | string; node_name: string }) {
  return request.post("/recycle/recycle_device_model_dict/ensure_child", data, {
    showErrorMessage: true,
  });
}

export function editRecycleDeviceModelDict(id: number | string, data: Record<string, any>) {
  return request.put(`/recycle/recycle_device_model_dict/${id}`, data, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

export function deleteRecycleDeviceModelDict(id: number | string) {
  return request.delete(`/recycle/recycle_device_model_dict/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

export function quickAddRecycleDeviceModelDict(data: Record<string, any>) {
  return request.post("/recycle/recycle_device_model_dict/quick_add", data, {
    showErrorMessage: true,
  });
}

export function importExternalRecycleDeviceModelDict(data: Record<string, any>) {
  return request.post("/recycle/recycle_device_model_dict/external_import", data, {
    showErrorMessage: true,
  });
}

export function uploadRecycleDeviceModelImport(data: FormData) {
  return request.post("/recycle/recycle_device_model_dict/import_upload", data, {
    headers: { "Content-Type": "multipart/form-data" },
    showErrorMessage: true,
  });
}

export function getRecycleDeviceModelImportTasks(params: Record<string, any> = {}) {
  return request.get("/recycle/recycle_device_model_dict/import_tasks", { params });
}

export function getRecycleDeviceModelImportTask(id: number | string) {
  return request.get(`/recycle/recycle_device_model_dict/import_tasks/${id}`);
}

export function retryRecycleDeviceModelImportTask(id: number | string) {
  return request.post(`/recycle/recycle_device_model_dict/import_tasks/${id}/retry`, {}, {
    showErrorMessage: true,
  });
}

export function deleteRecycleDeviceModelImportTask(id: number | string) {
  return request.delete(`/recycle/recycle_device_model_dict/import_tasks/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

export function updateRecycleDeviceModelDictSort(data: Record<string, any>) {
  return request.post("/recycle/recycle_device_model_dict/sort/update", data, {
    showErrorMessage: true,
  });
}

export function getTemplateBindingInfo(params: Record<string, any>) {
  return request.get("/recycle/template_binding/info", { params });
}

export function saveTemplateBinding(data: Record<string, any>) {
  return request.post("/recycle/template_binding/save", data, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

export function resetTemplateBinding(data: Record<string, any>) {
  return request.post("/recycle/template_binding/reset", data, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}
