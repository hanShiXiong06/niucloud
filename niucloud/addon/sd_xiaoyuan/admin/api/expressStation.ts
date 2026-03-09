import request from '@/utils/request'

// 获取快递站点列表
export function getExpressStationList(params: any) {
    return request.get('sd_xiaoyuan/express_station/list', params)
}

// 获取快递站点详情
export function getExpressStationInfo(id: number) {
    return request.get(`sd_xiaoyuan/express_station/info/${id}`)
}

// 添加快递站点
export function addExpressStation(data: any) {
    return request.post('sd_xiaoyuan/express_station/add', data)
}

// 编辑快递站点
export function editExpressStation(id: number, data: any) {
    return request.put(`sd_xiaoyuan/express_station/edit/${id}`, data)
}

// 删除快递站点
export function delExpressStation(id: number) {
    return request.delete(`sd_xiaoyuan/express_station/del/${id}`)
}

// 修改快递站点状态
export function setExpressStationStatus(id: number, status: number) {
    return request.put(`sd_xiaoyuan/express_station/status/${id}`, { status })
}

// 获取所有快递站点(不分页)
export function getAllExpressStations(params?: any) {
    return request.get('sd_xiaoyuan/express_station/all', params)
}
