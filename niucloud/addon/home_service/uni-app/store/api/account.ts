import request from '@/utils/request'

/**
 * 获取日账单统计数据
 * @returns Promise
 */
export const getDayBillStat = () => {
  return request.get('/home_service/store/statistics/dayBillStat')
}

/**
 * 获取日账单分页数据
 */
export function getDayBillPage(params: Record<string, any>) {
  return request.get('/home_service/store/statistics/dayBillPage', params)
}

/**
 * 获取账户余额
 * @returns Promise
 */
export const getAccountBalance = () => {
  return request.get('/home_service/store/account/balance')
}

/**
 * 获取账单列表
 * @param params 查询参数
 * @returns Promise
 */
export function getBillList(params: Record<string, any>) {
  return request.get('/home_service/store/account/bill_list', params)
}

/**
 * 获取账户类型列表   ok
 * @returns Promise
 */
export const getAccountTypeList = () => {
  return request.get('/home_service/store/account/type')
}

/**
 * 获取账户状态列表  ok
 * @returns Promise
 */
export const getAccountStatusList = () => {
  return request.get('/home_service/store/account/status')
}

/**
 * 获取师傅账户列表  ok
 * @param params 查询参数
 * @returns Promise
 */
export function storeAccountList(params: Record<string, any>) {
  return request.get('/home_service/store/account/storeAccountList', params)
}


/** 
 * 获取统计图数据
 * @param params 查询参数
 * @returns Promise
 */
export function getIncomeAndExpenseStatChart(params: Record<string, any>) {
  return request.get('/home_service/store/statistics/incomeAndExpenseStatChart', params)
}

/**
 * 获取日统计数据
 * @param params 查询参数
 * @returns Promise
 */
export function getIncomeAndExpenseStat(params: Record<string, any>) {
  return request.get('/home_service/store/statistics/incomeAndExpenseStat', params)
}

/**
 * 获取订单日统计数据
 * @param params 查询参数
 * @returns Promise
 */
export function getDayOrderStat(params: Record<string, any>) {
  return request.get('home_service/store/statistics/dayOrderStat', params)
}


/**
 * 获取订单月统计数据
 * @param params 查询参数
 * @returns Promise
 */
export function getMonthOrderStat(params: Record<string, any>) {
  return request.get('home_service/store/statistics/monthOrderStat', params)
}

/**
 * 获取订单统计数据明细
 * @param params 查询参数
 * @returns Promise
 */
export function getMonthorderPage(params: Record<string, any>) {
  return request.get('home_service/store/statistics/orderPage', params)
}




/********************************************提现配置******************************************* */


/**
 * 获取提现账户列表
 */
export function getCashOutAccountList(data: AnyObject) {
    return request.get(`home_service/store/cash_out_account`, data)
}


/**
 * 添加提现账户
 */
export function addCashoutAccount(data: AnyObject) {
    return request.post('home_service/store/cash_out_account', data, { showSuccessMessage: true, showErrorMessage: true })
}

/**
 * 添加提现账户
 */
export function editCashoutAccount(data: AnyObject) {
    return request.put(`home_service/store/cash_out_account/${ data.account_id }`, data, {
        showSuccessMessage: true,
        showErrorMessage: true
    })
}

/**
 * 删除提现账户
 */
export function deleteCashoutAccount(accountId: number) {
    return request.delete(`home_service/store/cash_out_account/${ accountId }`, { showSuccessMessage: true, showErrorMessage: true })
}

/**
 * 提现配置
 */
export function cashOutConfig() {
    return request.get('home_service/cash_out/config')
}

/**
 * 获取提现账户信息
 */
export function getCashoutAccountInfo(data: AnyObject) {
    return request.get(`home_service/store/cash_out_account/${ data.account_id }`, {})
}


/**
 * 获取首条提现账户信息
 */
export function getFirstCashOutAccountInfo(data: AnyObject) {
    return request.get('home_service/store/cash_out_account/first_info', data)
}


/**
 * 申请余额提现
 */
export function cashOutApply(data: AnyObject) {
    return request.post('home_service/store/cash_out/apply', data, { showSuccessMessage: false, showErrorMessage: true })
}


