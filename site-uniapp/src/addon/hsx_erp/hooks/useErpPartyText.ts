export function erpPartyNames(row: any) {
    return {
        partyName: String(row?.party_name || row?.counterparty_name || '').trim(),
        memberName: String(row?.member_name || row?.nickname || row?.username || '').trim(),
    }
}

/** 主体名是交易归属，会员名是实际联系人；相同时只展示一次。 */
export function erpPartyDisplayName(row: any, fallback = '-') {
    const { partyName, memberName } = erpPartyNames(row)
    if (!partyName && !memberName) return fallback
    if (!partyName || !memberName || partyName === memberName) return partyName || memberName
    return `${partyName} · ${memberName}`
}
