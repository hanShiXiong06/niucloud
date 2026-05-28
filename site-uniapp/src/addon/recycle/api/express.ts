import request from '@/utils/request'

export function getExpressOrderRecordList(params: Record<string, any>) {
    return request.get('recycle/express_order_record/lists', params)
}

export function getExpressOrderRecordInfo(id: number | string) {
    return request.get(`recycle/express_order_record/${id}`)
}

export function updateExpressOrderActualInfo(data: Record<string, any>) {
    return request.post('recycle/express_order_record/update_actual_info', data)
}

export function getExpressOrderStatistics(params: Record<string, any>) {
    return request.get('recycle/express_order_record/statistics', params)
}
