import request from '@/utils/request'

export const getMarketingCampaigns = (params: any) => request.get('/marketing/campaigns', { params })
export const getMarketingCampaign = (id: number) => request.get(`/marketing/campaigns/${id}`)
export const getMarketingMetadata = () => request.get('/marketing/campaigns/metadata')
export const getMarketingProviderOptions = (params: any) => request.get('/marketing/campaigns/provider-options', { params })
export const addMarketingCampaign = (data: any) => request.post('/marketing/campaigns', data)
export const editMarketingCampaign = (id: number, data: any) => request.put(`/marketing/campaigns/${id}`, data)
export const deleteMarketingCampaign = (id: number) => request.delete(`/marketing/campaigns/${id}`)
export const changeMarketingCampaignStatus = (id: number, status: number) => request.put(`/marketing/campaigns/${id}/status`, { status })
export const getMarketingRewards = (params: any) => request.get('/marketing/rewards', { params })
export const retryMarketingReward = (id: number) => request.post(`/marketing/rewards/${id}/retry`)
export const getMarketingFacts = (params: any) => request.get('/marketing/facts', { params })
export const retryMarketingFact = (id: number) => request.post(`/marketing/facts/${id}/retry`)
