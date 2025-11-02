import request from '@/utils/request'

/**
 * 获取关注用户分页列表
 */
export function getFollowList(params: Record<string, any>) {
    return request.get(`sow_community/follow/member`, params)
}

/**
 * 获取粉丝用户分页列表
 */
export function getFollowFans(params: Record<string, any>) {
    return request.get(`sow_community/follow/fans`, params)
}

/**
 * 取消关注
 */
export function follow(params: Record<string, any>) {
    return request.post(`sow_community/follow`, params, { showSuccessMessage: false })
}

/**
 * 获取社区分类列表
 */
export function getCategoryList() {
    return request.get(`sow_community/category/list`)
}

/**
 * 获取社区内容分页列表
 */
export function getContentList(params: Record<string, any>) {
    return request.get(`sow_community/content`, params)
}

/**
 * 获取社区内容分页列表
 */
export function getContentMyList(params: Record<string, any>) {
    return request.get(`sow_community/content/my/list`, params)
}

/**
 * 获取获取推荐用户列表
 */
export function getRecommendList(params: Record<string, any>) {
    return request.get(`sow_community/member/recommend`, params)
}

/**
 * 社区内容点赞
 */
export function setContentLike(params: Record<string, any>) {
    return request.put(`sow_community/content/like/${ params.content_id }`, params, { showSuccessMessage: false })
}

/**
 * 个人主页用户信息
 */
export function getMember(params: Record<string, any>) {
    return request.get(`sow_community/member`, params)
}

/**
 * 获取个人主页我赞过的内容分页列表
 */
export function getContentLike(params: Record<string, any>) {
    return request.get(`sow_community/content/like/list`, params)
}
