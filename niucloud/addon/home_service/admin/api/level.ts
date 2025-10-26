import request from '@/utils/request'
/**
 * 获取等级（不分页）
 * @returns
 */
export function getLevelList(params: Record<string, any>) {
    return request.get(`home_service/technician_level`,{params})
}



/**
 * 删除等级
 * @returns
 */
export function deleteLevel(id: number) {
    return request.delete(`home_service/technician_level/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}



/**
 * 等级详情
 * @returns
 */
export function getLevelDetail(id: number) {
    return request.get(`home_service/technician_level/${ id }`)
}


/**
 * 获取等级权重
 */
export function getFenxiaoLevelNum() {
    return request.get('home_service/technician_level/level_num');
}


/**
 * 添加等级
 * @param params
 * @returns
 */
export function addFenxiaoLevel(params: Record<string, any>) {
    return request.post('home_service/technician_level', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑等级
 * @param params
 * @returns
 */
export function editFenxiaoLevel(params: Record<string, any>) {
    return request.put(`home_service/technician_level/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 获取分类树
 */
export function getLevelTree() {
    return request.get('home_service/category/list');
}

