import request from '@/utils/request'

// 发送系统消息
export function sendMessage(data: any) {
    return request.post('sd_xiaoyuan/message/send', data)
}

// 批量发送系统消息
export function batchSendMessage(data: any) {
    return request.post('sd_xiaoyuan/message/batch_send', data)
}

// 获取消息类型列表
export function getMessageTypeList() {
    return request.get('sd_xiaoyuan/message/type_list')
}
