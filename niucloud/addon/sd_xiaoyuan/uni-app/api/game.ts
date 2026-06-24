import request from '@/utils/request'

// 游戏陪玩列表
export function getGameList(params: any) {
    return request.get('sd_xiaoyuan/game_companion/list', params)
}

// 游戏陪玩详情
export function getGameDetail(params: any) {
    return request.get('sd_xiaoyuan/game_companion/detail', params)
}

// 发布陪玩
export function publishGame(data: any) {
    return request.post('sd_xiaoyuan/game_companion/publish', data)
}

// 编辑陪玩
export function editGame(data: any) {
    return request.post('sd_xiaoyuan/game_companion/edit', data)
}

// 上架/下架
export function setGameStatus(data: any) {
    return request.post('sd_xiaoyuan/game_companion/set_status', data)
}

// 删除
export function delGame(data: any) {
    return request.post('sd_xiaoyuan/game_companion/del', data)
}

// 我的陪玩列表
export function getMyGameList(params: any) {
    return request.get('sd_xiaoyuan/game_companion/my', params)
}

// 游戏类型列表
export function getGameTypes() {
    return request.get('sd_xiaoyuan/game_companion/game_types')
}

// 服务类型列表
export function getServiceTypes() {
    return request.get('sd_xiaoyuan/game_companion/service_types')
}
