/** Framework member identity, never a fabricated merchant/account number. */
export function profileMemberNo(member: Record<string, any> = {}) {
    return typeof member.member_no === 'string' ? member.member_no.trim() : ''
}
export function profileMemberIdentifier(member: Record<string, any> = {}) {
    const number = profileMemberNo(member)
    if (number) return { label:'UID', value:number }
    const id = Number(member.member_id)
    // Older/test accounts may not have a framework member_no. Show their real
    // account ID explicitly as ID; never invent an NIU number or rewrite data.
    return { label:'ID', value:Number.isSafeInteger(id) && id > 0 ? String(id) : '' }
}
export function profileMemberBalance(member: Record<string, any> = {}) {
    const cents = (value: unknown) => {
        const text = String(value ?? '')
        if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return null
        const [whole, decimal = ''] = text.split('.')
        const number = Number(whole) * 100 + Number(decimal.padEnd(2, '0'))
        return Number.isSafeInteger(number) ? number : null
    }
    const balance = cents(member.balance), money = cents(member.money)
    if (balance === null || money === null || !Number.isSafeInteger(balance + money)) return '—'
    return ((balance + money) / 100).toFixed(2)
}
export function profileIdentityStyle(value: unknown) { return value === 'member' ? 'member' : 'store' }
