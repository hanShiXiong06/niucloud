import request from '@/utils/request'


/**
 * 获取文章详情
 */
export function getArticleInfo(id: number) {
    return request.get(`article/site/article/${ id }`)
}

/**
 * 文章列表
 */
export function getArticlePage(params: Record<string, any>) {
    return request.get('article/site/article', params)
}


/**
 * 获取资讯列表
 * @returns
 */
export function getArticleList(params: Record<string, any>) {
    return request.get('mall/site/stat/article', params)
}