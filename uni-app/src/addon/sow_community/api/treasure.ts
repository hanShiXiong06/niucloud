import request from '@/utils/request'

/**
 * 社区宝贝库类型列表
 */
export function getTreasureType() {
    return request.get(`sow_community/treasure/type`)
}

/**
 * 社区话题列表
 */
export function getTreasureList(params: Record<string, any>) {
    return request.get(`sow_community/treasure`, params)
}

/**
 * 社区宝贝内容分页列表
 */
export function getTreasureContent(params: Record<string, any>) {
    return request.get(`sow_community/treasure/content`, params)
}
