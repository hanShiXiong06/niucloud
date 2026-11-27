import request from '@/utils/request'

export function getSiteUserList(params: Record<string, any>) {
    return request.get('site/user', params)
}

export function getSiteUserInfo(uid: number) {
    return request.get(`site/user/${uid}`)
}

export function addSiteUser(data: Record<string, any>) {
    return request.post('site/user', data, { showSuccessMessage: true })
}

export function editSiteUser(uid: number, data: Record<string, any>) {
    return request.put(`site/user/${uid}`, data, { showSuccessMessage: true })
}

export function lockSiteUser(uid: number) {
    return request.put(`site/user/lock/${uid}`, {}, { showSuccessMessage: true })
}

export function unlockSiteUser(uid: number) {
    return request.put(`site/user/unlock/${uid}`, {}, { showSuccessMessage: true })
}

export function deleteSiteUser(uid: number) {
    return request.delete(`site/user/${uid}`, {}, { showSuccessMessage: true })
}

export function getRoleList(params: Record<string, any>) {
    return request.get('sys/role', params)
}

export function getRoleInfo(roleId: number) {
    return request.get(`sys/role/${roleId}`)
}

export function getAllRoles() {
    return request.get('sys/role/all')
}

export function addRole(data: Record<string, any>) {
    return request.post('sys/role', data, { showSuccessMessage: true })
}

export function editRole(roleId: number, data: Record<string, any>) {
    return request.put(`sys/role/${roleId}`, data, { showSuccessMessage: true })
}

export function updateRoleStatus(roleId: number, status: number) {
    return request.put('sys/role/status', { role_id: roleId, status }, { showSuccessMessage: true })
}

export function deleteRole(roleId: number) {
    return request.delete(`sys/role/${roleId}`, {}, { showSuccessMessage: true })
}

export function getSiteMenus() {
    return request.get('site/site/menu')
}
