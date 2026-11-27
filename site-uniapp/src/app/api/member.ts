import request from '@/utils/request'


/**
 * 会员信息修改
 */
export function modifyMember(data: AnyObject) {
    return request.put(`member/modify/${ data.field }`, data, { showErrorMessage: true })
}

/**
 * 登录会员绑定手机号
 */
export function bindMobile(data: AnyObject) {
    if (uni.getStorageSync('pid')) {
        data.pid = uni.getStorageSync('pid');
    }
    return request.put('member/mobile', data, { showErrorMessage: true })
}

/**
 * 获取手机号
 */
export function getMobile(data: AnyObject) {
    if (uni.getStorageSync('pid')) {
        data.pid = uni.getStorageSync('pid');
    }
    return request.put('member/getmobile', data, { showErrorMessage: true })
}

/***************************************************** 会员列表 ****************************************************/
/**
 * 获取店铺会员列表
 * @param params
 * @returns
 */
export function getShopMember(params: Record<string, any>) {
    return request.get(`member/member`, params)
}

/**
 * 获取店铺会员详情
 * @param member_id 会员id
 * @returns
 */
export function getShopMemberInfo(member_id: number) {
    return request.get(`member/member/${member_id}`);
}

/**
 * 获取店铺会员优惠券
 * @param member_id 会员id
 * @returns
 */
export function getShopMemberCoupon(member_id: number) {
    return request.get(`mall/site/member/coupon/list/${member_id}`);
}

/**
 * 会员等级（全部，用于筛选/改等级）
 */
export function getMemberLevelAll() {
    return request.get(`member/level/all`);
}

/**
 * 会员标签（全部，用于筛选/打标签）
 */
export function getMemberLabelAll() {
    return request.get(`member/label/all`);
}

/**
 * 生成会员编号（添加会员用）
 */
export function getMemberNo() {
    return request.get(`member/memberno`);
}

/**
 * 添加会员
 */
export function addShopMember(data: Record<string, any>) {
    return request.post(`member/member`, data, { showSuccessMessage: true });
}

/**
 * 修改会员单个字段（member_level 等级 / member_label 标签 / nickname / mobile 等）
 * 后端读 data.value 作为新值：等级 value=level_id，标签 value=[label_ids]
 * @param member_id
 * @param field   字段名
 * @param value   新值
 */
export function editMemberField(member_id: number, field: string, value: any) {
    return request.put(`member/member/modify/${member_id}/${field}`, { value }, { showSuccessMessage: true });
}

/**
 * 会员状态（锁定/解锁）status:1 正常 / 0 锁定
 */
export function setMemberStatus(status: number, member_ids: number[]) {
    return request.put(`member/setstatus/${status}`, { status, member_ids }, { showSuccessMessage: true });
}

/***************************************************** 会员标签 CRUD ****************************************************/
/**
 * 标签列表（分页）
 */
export function getMemberLabelList(params: Record<string, any>) {
    return request.get(`member/label`, params)
}
/**
 * 新建标签
 */
export function addMemberLabel(data: Record<string, any>) {
    return request.post(`member/label`, data, { showSuccessMessage: true })
}
/**
 * 修改标签
 */
export function updateMemberLabel(label_id: number, data: Record<string, any>) {
    return request.put(`member/label/${label_id}`, data, { showSuccessMessage: true })
}
/**
 * 删除标签
 */
export function deleteMemberLabel(label_id: number) {
    return request.delete(`member/label/${label_id}`, { showSuccessMessage: true })
}