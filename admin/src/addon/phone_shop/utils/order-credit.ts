/** 挂账只是原成交约定；实际收款及完成资格只消费 ERP 的只读核对结果。 */
export const isCreditOrder = (order: any): boolean => order?.payment_mode === 'offline_credit'
export const creditFinance = (order: any): Record<string, any> => order?.finance_snapshot || {}
export const creditCanComplete = (order: any): boolean => !isCreditOrder(order) || (
    creditFinance(order).available === true && creditFinance(order).matched === true && creditFinance(order).can_complete === true
)
export const creditStatusLabel = (order: any): string => String(creditFinance(order).status_name || '收款状态待核实')
export const creditMessage = (order: any): string => String(creditFinance(order).message || '请刷新核对 ERP 收款状态；收齐前不能完成订单，请勿重复收款。')
export const creditMoney = (value: any): string => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value).toFixed(2) : '—'
export const creditMethods = (order: any): string => (Array.isArray(creditFinance(order).payment_methods) ? creditFinance(order).payment_methods : []).map((item: any) => item.name).filter(Boolean).join('、')
export const creditAccounts = (order: any): string => (Array.isArray(creditFinance(order).capital_accounts) ? creditFinance(order).capital_accounts : []).map((item: any) => item.name).filter(Boolean).join('、')
