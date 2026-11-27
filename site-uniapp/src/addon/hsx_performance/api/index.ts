import request from '@/utils/request'

export const getMobilePerformanceOutputOverview = (params: Record<string, any>) => request.get('performance/output/overview', params)
export const getMobilePerformanceOutputEmployees = (params: Record<string, any>) => request.get('performance/output/employees', params)
export const getMobilePerformanceOutputEmployee = (uid: number, params: Record<string, any>) => request.get(`performance/output/employees/${uid}`, params)
export const getMobilePerformanceOutputFacts = (params: Record<string, any>) => request.get('performance/output/facts', params)
export const getMobilePerformanceReports = (params: Record<string, any>) => request.get('performance/reports', params)
export const getMobilePerformanceReport = (id: number) => request.get(`performance/reports/${id}`)
