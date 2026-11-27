import {
    entityAccounts,
    experienceGateEnabled,
    experienceText,
    membershipBenefits,
    membershipCycles
} from './entity-membership'

type Row = Record<string, any>
export function membershipOfferCycle(tier: Row, key: string) {
    const periods = membershipCycles(tier)
    return periods.find((period: Row) => period.key === key) || periods[0]
}
export function membershipOfferMoney(value: unknown) {
    return /^\d+\.\d{2}$/.test(String(value)) ? `¥${value}` : '待核对'
}
/** Compare the published catalogue, not a quote or an entitlement grant. */
export function membershipComparisonRows(tiers: Row[], cycle: string) {
    const textCell = (text: string) => ({ text, included: null as boolean | null })
    const rows = [
        {
            key: 'service',
            title: '服务费 / 周期',
            cells: tiers.map((tier) => {
                const period = membershipOfferCycle(tier, cycle)
                return textCell(period ? `${membershipOfferMoney(period.price)} / ${period.name}` : '暂未开放')
            })
        },
        { key: 'accounts', title: '账号名额', cells: tiers.map((tier) => textCell(entityAccounts(tier))) },
        {
            key: 'deposit',
            title: '等级押金',
            cells: tiers.map((tier) => textCell(membershipOfferMoney(tier.deposit_amount)))
        },
        {
            key: 'experience',
            title: '经验门槛',
            cells: tiers.map((tier) =>
                textCell(
                    !experienceGateEnabled(tier) || tier.is_entry === true
                        ? '无门槛'
                        : `${experienceText(tier.experience_required)} 点`
                )
            )
        }
    ]
    const titles = new Map<string, string>()
    tiers.forEach((tier) => membershipBenefits(tier).forEach((benefit) => titles.set(benefit.key, benefit.title)))
    titles.forEach((title, key) =>
        rows.push({
            key: `benefit_${key}`,
            title,
            cells: tiers.map((tier) => {
                const benefit = membershipBenefits(tier).find((item) => item.key === key)
                return benefit
                    ? { text: benefit.enabled ? '包含' : '不包含', included: benefit.enabled }
                    : textCell('未提供')
            })
        })
    )
    return rows
}
