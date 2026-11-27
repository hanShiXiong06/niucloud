import { api, type Row } from './console'

// 保留旧的只读入口，所有页面统一走插件实际路由。
export const getTaskList = (params: Row) => api.tasks(params).then(data => ({data}))
export const getTaskInfo = (id: number) => api.task(id).then(data => ({data}))
export const getTaskStatistics = () => api.statistics().then(data => ({data: data}))
export const retryTask = (id: number) => api.retry(id).then(data => ({data}))
