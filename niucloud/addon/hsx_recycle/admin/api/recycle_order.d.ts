interface ApiResponse<T = any> {
    code: number
    msg: string
    data: T
}

interface RecycleOrderListParams {
    page: number
    limit: number
    order_id?: string
    send_username?: string
    telphone?: string
    status?: number | string
}

interface RecycleOrderUpdateParams {
    status: number
    old_status?: number
    receiver?: string
    receive_remark?: string
    receive_images?: string[]
}

interface DeviceStatusUpdateParams {
    order_id: number
    status: number
    check_result?: string
    final_price?: number
    price_remark?: string
}

interface RecycleDeviceAddParams {
    order_id: number
    imei: string
    model: string
    initial_price: number
    remark?: string
}

declare module '@/api/recycle/recycle_order' {
    export function getRecycleOrderList(params: RecycleOrderListParams): Promise<ApiResponse>
    export function updateRecycleOrder(id: string, data: RecycleOrderUpdateParams): Promise<ApiResponse>
    export function deleteRecycleOrder(id: number): Promise<ApiResponse>
    export function updateDeviceStatus(id: string, data: DeviceStatusUpdateParams): Promise<ApiResponse>
    export function addRecycleDevice(data: RecycleDeviceAddParams): Promise<ApiResponse>
} 