import request from '@/utils/request'

// 仓库/库位树
export function getAssignTree() {
    return request.get('erp/location_assign/tree')
}

// 可分配员工
export function getAssignStaffOptions() {
    return request.get('erp/location_assign/staff_options')
}

// 分配列表（可按 uid / location_id 过滤）
export function getAssignList(params: Record<string, any> = {}) {
    return request.get('erp/location_assign/lists', { params })
}

// 设置某库位的负责人组
export function setLocationStaff(locationId: number, data: { warehouse_id: number; uids: number[] }) {
    return request.post(`erp/location_assign/location/${locationId}/staff`, data)
}

// 设置某员工负责的库位组
export function setStaffLocations(uid: number, data: { locations: Array<{ warehouse_id: number; location_id: number }> }) {
    return request.post(`erp/location_assign/staff/${uid}/locations`, data)
}
