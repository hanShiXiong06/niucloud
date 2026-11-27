import request from '@/utils/request'

export function getPrintSceneManualActions(params: Record<string, any> = {}) {
    return request.get('recycle/print_scene/manual_actions', params, {
        showSuccessMessage: false
    })
}

export function getPrintScenePlan(sceneKey: string, params: Record<string, any> = {}) {
    return request.get(`recycle/print_scene/${ sceneKey }/plan`, params, {
        showErrorMessage: true,
        showSuccessMessage: false
    })
}

export function printByScene(sceneKey: string, data: Record<string, any> = {}) {
    return request.post(`recycle/print_scene/${ sceneKey }/print`, data, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}
