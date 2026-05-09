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
    follow_official_account: {
        enabled: number
        wechat_name: string
        qr_code: string
        title: string
        content: string
    }
    customer_service: {
        enabled: number
        type: 'wechat' | 'qrcode'
        qrcode: string
        title: string
        content: string
    }
    allow_user_reject_sale: number
    price_detail_theme: {
        template_key: string
        theme_name: string
        colors: Record<string, string>
    }
}

export function getOrderSubmitConfig() {
    return request.get('recycle/order_submit_config')
}

export function saveOrderSubmitConfig(params: OrderSubmitConfig) {
    return request.post('recycle/order_submit_config', params, { showSuccessMessage: true })
}
