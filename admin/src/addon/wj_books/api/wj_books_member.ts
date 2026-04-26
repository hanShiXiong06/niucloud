import request from '@/utils/request'

/**
 * 增加会员余额（不可提现余额）
 * @param params 参数对象，包括 member_id, adjust, account_data, adjust_type, memo 等
 * @returns Promise
 */
export function addMemberBalanceForBooks(params: Record<string, any>) {
    return request.post(`member/account/balance`, params, { showSuccessMessage: true })
}

/**
 * 增加会员可提现余额
 * @param params 参数对象，包括 member_id, account_data, memo, from_type, related_id 等
 * @returns Promise
 */
export function addMemberMoneyForBooks(params: Record<string, any>) {
    // 使用我们在插件中新增的路由
    return request.post(`wj_books_member/money`, params, { showSuccessMessage: true })
}

/**
 * 获取会员信息
 * @param member_id 会员ID
 * @returns Promise
 */
export function getMemberInfoForBooks(member_id: number) {
    return request.get(`member/member/${member_id}`);
} 