import request from '@/utils/request'
export const getPickupNoticeConfig = () => request.get('hsx_recycle/pickup_notice/config')
export const setPickupNoticeConfig = (data: any) => request.put('hsx_recycle/pickup_notice/config', data)
