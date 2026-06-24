import request from '@/utils/request'

export const getServiceCardList = () => {
    return request.get('sd_xiaoyuan/service_card/list')
}

export const saveServiceCards = (list: any[]) => {
    return request.post('sd_xiaoyuan/service_card/save', { list })
}
