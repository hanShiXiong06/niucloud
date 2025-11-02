import request from '@/utils/request'

/**
 * 添加内容
 */
export function setContent(params: Record<string, any>) {
    return request.post('sow_community/content', params)
}

/**
 * 编辑
 */
export function editContent(params: Record<string, any>) {
    return request.put(`sow_community/content/${ params.content_id }`, params)
}

/**
 * 删除内容
 * @param content_id
 * @returns
 */
export function deleteContent(content_id: number) {
    return request.delete(`sow_community/content/${ content_id }`)
}

/**
 * 获取内容详情
 */
export function getContentDetail(content_id: any) {
    return request.get(`sow_community/content/${ content_id }`)
}

/**
 *  内容足迹添加
 */
export function browse(content_id: any) {
    return request.put(`sow_community/content/browse/${ content_id }`)
}

/**
 *  社区内容分享
 */
export function share(content_id: any) {
    return request.put(`sow_community/content/share/${ content_id }`)
}

/**
 * 评论列表
 */
export function getCommentList(params: Record<string, any>) {
    return request.get(`sow_community/comment`, params)
}

/**
 * 添加评论
 */
export function setComment(params: Record<string, any>) {
    return request.post('sow_community/comment', params, { showSuccessMessage: true })
}

/**
 * 评论删除
 */
export function deleteComment(comment_id: number) {
    return request.delete(`sow_community/comment/${ comment_id }`, {}, { showSuccessMessage: true })
}

/**
 * 评论点赞
 */
export function setCommentLike(params: Record<string, any>) {
    return request.put(`sow_community/comment/like/${ params.comment_id }`, params, { showSuccessMessage: false })
}
