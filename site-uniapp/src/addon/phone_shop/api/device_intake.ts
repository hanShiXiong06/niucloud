import request from '@/utils/request'

export function getMobileDeviceIntakeList(params: Record<string, any> = {}) {
    return request.get('phone_shop/device_intake', params)
}

export function getMobileDeviceIntakePolicy() {
    return request.get('phone_shop/device_intake/material_policy')
}

export function previewMobileDeviceIntake(intakeId: number) {
    return request.post('phone_shop/device_intake/preview', { intake_id: intakeId })
}

export function buildMobileDeviceIntake(data: Record<string, any>) {
    return request.post('phone_shop/device_intake/build', data, { showSuccessMessage: true, showErrorMessage: true })
}
