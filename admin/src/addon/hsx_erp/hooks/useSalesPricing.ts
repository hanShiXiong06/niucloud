/** 仅展示预览，真正成交/保存价格始终由后端重新计算。 */
export function salesPricePreview(policy: any, value: any): string {
    if (Number(policy?.enabled) !== 1) return '对外销售价，不改变采购成本'
    const base = Math.round(Number(value) * 100)
    if (!(base > 0)) return '填写最高等级会员的最低售价，普通价与各会员价自动计算'
    const levels = [{ level_no: 0, level_name: '普通客户' }, ...(policy.levels || [])]
    return levels.map((lv: any) => {
        let rule = policy.rules?.[lv.level_no] || { type: 'fixed', value: 0 }
        if (lv.level_no === Number(policy.base_level_no)) rule = {type:'fixed',value:0}
        else rule = (rule.bands || []).find((v: any) => base >= Math.round(Number(v.min) * 100) && (v.max == null || v.max === '' || base < Math.round(Number(v.max) * 100))) || rule
        const amount = Math.round(Number(rule.value || 0) * 100)
        const price = base + (rule.type === 'percent' ? Math.round(base * amount / 10000) : amount)
        return `${lv.level_name} ¥${(price / 100).toFixed(2)}`
    }).join(' · ')
}
export function salesPriceInput(asset: any): number {
    return Number(Number(asset?.sales_pricing?.enabled) === 1 ? asset?.estimate_sale_price || 0 : asset?.retail_price || 0)
}
export function salesPriceLabel(asset: any): string {
    return Number(asset?.sales_pricing?.enabled) === 1 ? '基准售价' : '销售定价'
}
