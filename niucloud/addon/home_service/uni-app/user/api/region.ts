import request from '@/utils/request'

/**
 * 获取区域列表
 * @param params
 * @returns
 */
export function getRegionList(params: Record<string, any>) {
    return request.get(`home_service/region`, params)
}

/**
 * 获取省份列表
 * @returns
 */
export function getProvinceList() {
    return request.get(`home_service/region/province`)
}

/**
 * 根据省份ID获取城市列表
 * @param provinceId
 * @returns
 */
export function getCityListByProvince(provinceId: number) {
    return request.get(`home_service/region/city`, { parent_id: provinceId })
}

/**
 * 根据城市ID获取区县列表
 * @param cityId
 * @returns
 */
export function getDistrictListByCity(cityId: number) {
    return request.get(`home_service/region/district`, { parent_id: cityId })
}