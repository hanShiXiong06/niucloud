export enum OrderStatus {
    PENDING = 0,          // 待处理
    PENDING_CONFIRM = 1,  // 待平台签收
    PENDING_CHECK = 2,    // 待质检
    CHECKING = 3,         // 质检中
    CHECKED = 4,         // 质检完成
    PENDING_PAYMENT = 5,  // 待打款
    COMPLETED = 6,       // 已完成
    CLOSED = -1          // 已关闭
}

export enum DeliveryType {
    MAIL = 'mail',
    SELF = 'self'
}

export interface ApiResponse<T> {
    code: number
    data: T
    msg: string
}

export interface OrderListData {
    total: number
    data: OrderItem[]
}

export interface OrderListResponse {
    total: number
    data: OrderItem[]
}

export interface OrderItem {
    id: number
    order_no: string
    status: OrderStatus
    delivery_type: DeliveryType
    send_username: string
    telphone: string
    create_at: number
}

export interface SearchForm {
    order_no: string
    status: OrderStatus | ''
    delivery_type: DeliveryType | ''
    create_time: string[]
}

export interface OrderAction {
    type: string
    name: string
}