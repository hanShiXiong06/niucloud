import request from '@/utils/request'

/** 插件维护的官方参考目录，不是本站账号已开通产品查询。 */
export const getPickupProductGuide = () => request.get('recycle/third_party_config/kuaidi100_guide')
