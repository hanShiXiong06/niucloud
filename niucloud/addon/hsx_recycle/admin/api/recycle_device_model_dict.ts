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

export function addRecycleDeviceModelDict(data: Record<string, any>) {
  return request.post("/recycle/recycle_device_model_dict", data, {
    showErrorMessage: true,
    showSuccessMessage: true,
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
