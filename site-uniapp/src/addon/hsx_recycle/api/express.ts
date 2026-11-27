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

export function getExpressOrderStatusOptions() {
    return request.get('recycle/express_order_record/status_options')
}

export function getExpressQuote(params: Record<string, any>) {
    return request.post('recycle/express_order/quote', params)
}

export function createExpressOrderDirect(params: Record<string, any>) {
    return request.post('recycle/express_order/create', params)
}

export function queryExpressTrack(params: Record<string, any>) {
    return request.get('recycle/device_query_api/express', params, {
        showErrorMessage: false,
        showSuccessMessage: false
    })
}
