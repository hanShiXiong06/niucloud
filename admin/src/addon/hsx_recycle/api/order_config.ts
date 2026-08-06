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
        logistics_vehicle: number
    }
    logistics_vehicle: {
        arrival_mode: 'half_day' | 'next_day'
        morning_cutoff: string
        same_day_time: string
        next_day_time: string
    }
    profile: {
        enabled: number
        payment_required: number
        payment_min_count: number
        id_card_required: number
    }
    payment: {
        mode: 'order' | 'device'
    }
    flow: {
        mode: 'order' | 'device'
    }
    platform_delivery: {
        display_name: string
        free_shipping_min_count: number
        provider: string
        provider_name: string
        provider_options: Array<{
            provider: string
            provider_name: string
            is_default: number
            support_quote?: boolean
            support_cancel?: boolean
            support_track?: boolean
        }>
        product_code: string
        product_name: string
        product_options: Array<{
            provider: string
            product_code: string
            product_name: string
            express_type?: string
            logo?: string
        }>
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
    order_detail: {
        show_inspection_result: number
        show_inspection_images: number
    }
    consignment: {
        enabled: number
        user_entry_enabled: number
        user_view_enabled: number
        transfer_confirm_required: number
        notice_enabled: number
        print_enabled: number
        show_service_fee: number
        user_title: string
        user_desc: string
    }
    work_wechat: {
        enabled: number
        channels: Record<string, {
            enabled: number
            name: string
            webhook_url: string
            dedupe_minutes: number
            daily_limit: number
            user_cooldown_hours?: number
        }>
    }
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
