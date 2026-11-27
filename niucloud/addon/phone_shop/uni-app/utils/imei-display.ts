/** 仅商品显示用，不改变原串号、检索值、订单或业务提交参数。 */
export function formatGoodsImei(value: unknown, tailOnly: unknown = false): string {
    const serial = typeof value === 'string' || typeof value === 'number' ? String(value).trim() : ''
    return tailOnly === true || Number(tailOnly) === 1 ? serial.slice(-6) : serial
}
