import request from '@/utils/request'

export interface OrderSubmitConfig {
    device_add_enabled: number
    notice: {
        enabled: number
        title: string
        content: string
    }
    default_count: number
    delivery_modes: {
        mail: number
        self: number
    }
    profile: {
        enabled: number
        payment_required: number
        payment_min_count: number
        id_card_required: number
    }
    platform_delivery: {
        display_name: string
        free_shipping_min_count: number
    }
}

export function getOrderSubmitConfig() {
    return request.get('recycle/order_submit_config')
}

export function saveOrderSubmitConfig(params: OrderSubmitConfig) {
    return request.post('recycle/order_submit_config', params, { showSuccessMessage: true })
}
