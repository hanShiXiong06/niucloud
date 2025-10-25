import request from "@/utils/request";

// 获取设备列表（支持筛选）
export const getDeviceList = async (params: any): Promise<any> => {
    return await request.get('recycle/excel/lists', { params })
}

// 获取品牌列表
export const getBrandList = async (): Promise<any> => {
    return await request.get('recycle/excel/brands')
}

// 获取统计信息
export const getStatistics = async (): Promise<any> => {
    return await request.get('recycle/excel/statistics')
}

// 获取图表数据
export const getChartData = async (params: any): Promise<any> => {
    return await request.get('recycle/excel/chart', { params })
}

// 导出Excel（暂时保留，可能用于数据验证）
export const exportExcel = async (params: any): Promise<any> => {
    return await request.get('recycle/excel/export', { params })
}