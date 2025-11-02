import request from '@/utils/request'

/**
 * 社区话题列表
 */
export function getTopicList(params: Record<string, any>) {
    return request.get(`sow_community/topic/list`, params)
}