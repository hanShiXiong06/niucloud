import request from '@/utils/request'
export const searchCooperationRegions = (keyword: string) => request.get('hsx_lead/cooperation/regions', { keyword }, { showErrorMessage: false })
