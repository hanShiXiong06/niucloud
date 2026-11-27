import request from '@/utils/request'

export const getMarketingOverview = () => request.get('marketing/overview')
export const getMarketingTasks = (params: Record<string, any>) => request.get('marketing/tasks', params)
export const claimMarketingTask = (id: number) => request.post(`marketing/tasks/${id}/claim`)
export const getMarketingRewards = (params: Record<string, any>) => request.get('marketing/rewards', params)
export const claimMarketingReward = (id: number) => request.post(`marketing/rewards/${id}/claim`)
