export type SubjectCycle = { key: string; name: string; months: number; enabled: boolean; price: string }
export type SubjectVisual = { preset:string;start_color:string;end_color:string;text_color:string;accent_color:string;angle:number;card_image:string;avatar_effect:boolean }
export type SubjectBenefit = {
    is_use: 0 | 1; is_entry: boolean; level_color: string; account_limit: number; unlimited: boolean;
    deposit_amount: string; experience_purchase_enabled: boolean; experience_unit_price: string;
    can_publish: boolean; can_accept: boolean; can_view_inventory: boolean; all_models: boolean;
    cycles: SubjectCycle[]; visual:SubjectVisual
}
export type EntityLevel = {
    code: string; level_id: number; name: string; rank: number; display_rank: number;
    is_entry: boolean; level_color: string; experience_required: string | null; experience_eligible: boolean | null;
    deposit_amount: string; experience_purchase_enabled: boolean; experience_unit_price: string;
    account_limit: number; unlimited: boolean; cycles: SubjectCycle[];
    capabilities: Record<string, boolean>; benefits: {key:string;title:string;desc:string;icon:string;enabled:boolean}[]
}
const enabled = (value: unknown) => value === true || value === 1
const validLevelColor = (value: unknown) => typeof value === 'string' && /^(?:#[\da-f]{3}|#[\da-f]{6})$/i.test(value)
const visualColorKeys=['start_color','end_color','text_color','accent_color'] as const
export const subjectVisualPresets=[{key:'auto',name:'自动配色'},{key:'bronze',name:'暖铜'},{key:'silver',name:'雾银'},{key:'gold',name:'流金'},{key:'sapphire',name:'蓝宝石'},{key:'amethyst',name:'紫晶'},{key:'obsidian',name:'曜石'},{key:'custom',name:'自定义'}] as const
export const subjectVisualPresetColors:Record<string,{start_color:string;end_color:string;text_color:string;accent_color:string}>={
    bronze:{start_color:'#F3E8D9',end_color:'#DDC2A0',text_color:'#64452F',accent_color:'#A47743'},
    silver:{start_color:'#F4F6FA',end_color:'#D9E0EA',text_color:'#435269',accent_color:'#7C8EA5'},
    gold:{start_color:'#FFF1C8',end_color:'#E5C581',text_color:'#6E4C18',accent_color:'#AF7B25'},
    sapphire:{start_color:'#EAF1FE',end_color:'#AFC8F0',text_color:'#264D7F',accent_color:'#3769AD'},
    amethyst:{start_color:'#F0EAF9',end_color:'#CDBBE5',text_color:'#65477F',accent_color:'#8665A9'},
    obsidian:{start_color:'#354153',end_color:'#192230',text_color:'#F4E6BC',accent_color:'#C9A967'},
    neutral:{start_color:'#F4F5F7',end_color:'#E9ECF0',text_color:'#334155',accent_color:'#64748B'},
}
export function normalizeSubjectVisual(value:unknown):SubjectVisual{
    const row=value&&typeof value==='object'&&!Array.isArray(value)?value as Record<string,any>:{}
    return{preset:row.preset===undefined?'auto':String(row.preset),...Object.fromEntries(visualColorKeys.map(key=>[key,row[key]===undefined||row[key]===null?'':String(row[key])])) as Pick<SubjectVisual,typeof visualColorKeys[number]>,angle:row.angle===undefined?135:typeof row.angle==='number'?row.angle:NaN,card_image:row.card_image===undefined?'':String(row.card_image),avatar_effect:row.avatar_effect===undefined?true:row.avatar_effect}
}
export function subjectVisualImageError(value:unknown):string{
    if(typeof value!=='string')return'卡面图片地址无效，请重新选择'
    if(!value)return''
    if(/^addon\/hsx_lead\/member-covers\/(?:bronze|silver|gold|sapphire|amethyst|obsidian)\.(?:png|gif)$/.test(value))return''
    if(value.length>1000||/[\s\x00-\x1f\x7f'"\\<>{}();]/.test(value)||/%2e/i.test(value)||value.split(/[/?#]/).includes('..'))return'卡面图片地址无效，请通过图片组件重新选择'
    if(/^https?:\/\//i.test(value)){try{const parsed=new URL(value);if(!parsed.hostname||parsed.username||parsed.password||parsed.pathname.split('/').includes('..'))return'卡面图片地址无效，请重新选择';return''}catch{return'卡面图片地址无效，请重新选择'}}
    return /^\/?(?:upload|uploads|attachment)\/[^?#]+$/.test(value)&&!value.split('/').includes('..')?'':'卡面图片请使用本站上传图片或有效HTTPS地址'
}
export function subjectVisualError(value:SubjectVisual):string{
    if(!value||!subjectVisualPresets.some(item=>item.key===value.preset))return'请选择有效的会员外观预设'
    for(const key of visualColorKeys)if(value[key]!==''&&!validLevelColor(value[key]))return'会员外观颜色仅支持#RGB或#RRGGBB'
    if(!Number.isInteger(value.angle)||value.angle<0||value.angle>360)return'渐变方向请填写0—360的整数'
    if(typeof value.avatar_effect!=='boolean')return'请检查头像标识效果开关'
    return subjectVisualImageError(value.card_image)
}
/** Preview only: auto is intentionally neutral because this editor has no authoritative display rank. */
export function subjectVisualPreview(value:SubjectVisual){
    const base=subjectVisualPresetColors[value.preset]||subjectVisualPresetColors[value.preset==='custom'?'bronze':'neutral']
    return{...value,...Object.fromEntries(visualColorKeys.map(key=>[key,value.preset==='custom'&&validLevelColor(value[key])?value[key]:base[key]])),angle:Number.isInteger(value.angle)&&value.angle>=0&&value.angle<=360?value.angle:135,card_image:subjectVisualImageError(value.card_image)?'':value.card_image}
}
/** Explicitly chosen decorative artwork, never an entitlement or a certification. */
export const subjectCoverThemes=subjectVisualPresets.filter(item=>['bronze','silver','gold','sapphire','amethyst','obsidian'].includes(item.key))
export function subjectCoverPath(theme:string,format:string='png'):string{
    return subjectCoverThemes.some(item=>item.key===theme)&&['png','gif'].includes(format)?`addon/hsx_lead/member-covers/${theme}.${format}`:''
}
export function subjectCoverSelection(value:SubjectVisual,theme:string,format:string='png'):SubjectVisual|null{
    const path=subjectCoverPath(theme,format),colors=subjectVisualPresetColors[theme]
    return path&&colors?{...normalizeSubjectVisual(value),...colors,preset:theme,card_image:path}:null
}
const money = (value: unknown) => /^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/.test(String(value)) && Number(value) <= 99999999.99
export const subjectCapabilityLabels = { can_publish: '发布客户需求', can_accept: '接收客户线索', can_view_inventory: '查看门店库存', all_models: '全型号交付能力' } as const
export function defaultSubjectCycles(): SubjectCycle[] {
    return [{ key: 'month', name: '月付', months: 1 }, { key: 'quarter', name: '季付', months: 3 }, { key: 'year', name: '年付', months: 12 }].map(row => ({ ...row, enabled: false, price: '0.00' }))
}
/** The editor keeps a private draft; neither defaults nor normalization mutate framework props. */
export function normalizeSubjectBenefit(value: unknown): SubjectBenefit {
    const row = value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, any> : {}
    return {
        is_use: enabled(row.is_use) ? 1 : 0,
        is_entry: enabled(row.is_entry), level_color: typeof row.level_color==='string'?row.level_color:'',
        deposit_amount: row.deposit_amount === undefined ? '0.00' : String(row.deposit_amount),
        experience_purchase_enabled: enabled(row.experience_purchase_enabled),
        experience_unit_price: row.experience_unit_price === undefined ? '0.00' : String(row.experience_unit_price),
        account_limit: row.account_limit === undefined ? 1 : Number(row.account_limit), unlimited: enabled(row.unlimited),
        can_publish: enabled(row.can_publish), can_accept: enabled(row.can_accept),
        can_view_inventory: enabled(row.can_view_inventory), all_models: enabled(row.all_models),
        visual:normalizeSubjectVisual(row.visual),
        cycles: Array.isArray(row.cycles) ? row.cycles.map((cycle: Record<string, any>) => ({
            key: String(cycle?.key || ''), name: String(cycle?.name || ''), months: Number(cycle?.months),
            enabled: enabled(cycle?.enabled), price: cycle?.price === undefined ? '0.00' : String(cycle.price)
        })) : defaultSubjectCycles()
    }
}
export function subjectBenefitError(value: SubjectBenefit): string {
    if (value.is_use !== 1) return ''
    if(typeof value.is_entry!=='boolean')return '请检查入门等级开关'
    if(!money(value.deposit_amount))return '等级押金请填写0或正数金额，最多两位小数'
    if(typeof value.experience_purchase_enabled!=='boolean')return '请检查补足经验开关'
    if(value.experience_purchase_enabled&&(!money(value.experience_unit_price)||Number(value.experience_unit_price)<=0))return '开启补足经验后，请填写大于0的每点经验价格，最多两位小数'
    if(value.level_color!==''&&!validLevelColor(value.level_color))return '等级颜色请选择有效的十六进制颜色'
    const visualError=subjectVisualError(value.visual);if(visualError)return visualError
    if (!Number.isInteger(value.account_limit) || value.account_limit < 1 || value.account_limit > 99999) return '总账号数请填写1—99999，人数包含负责人'
    if (!Array.isArray(value.cycles) || value.cycles.length > 50) return '购买周期请配置为不超过50项的列表'
    const keys = new Set<string>()
    for (const row of value.cycles) {
        if (!/^[A-Za-z][A-Za-z0-9_-]{0,23}$/.test(row.key) || keys.has(row.key)) return '购买周期标识无效或重复，请移除后重新添加该自定义周期'
        keys.add(row.key)
        if (!row.name.trim() || row.name.trim().length > 30) return '每个购买周期的名称请填写1—30个字符'
        if (!Number.isInteger(row.months) || row.months < 1 || row.months > 120) return `${row.name}：有效月数请填写1—120`
        if (row.enabled && (!money(row.price) || Number(row.price) <= 0)) return `${row.name}：开启后请填写大于0的价格，最多两位小数`
    }
    return ''
}
export function subjectBenefitPayload(value: SubjectBenefit): SubjectBenefit {
    return { ...value, deposit_amount: money(value.deposit_amount) ? Number(value.deposit_amount).toFixed(2) : value.deposit_amount,
        experience_unit_price: value.experience_purchase_enabled ? money(value.experience_unit_price) ? Number(value.experience_unit_price).toFixed(2) : value.experience_unit_price : '0.00',
        visual:{...value.visual}, cycles: value.cycles.map(row => ({ ...row, name: row.name.trim(), price: row.enabled && money(row.price) ? Number(row.price).toFixed(2) : '0.00' })) }
}
/** Read-only catalogue projected from framework membership levels; no local V-tier catalogue. */
export function normalizeEntityLevels(value: unknown): EntityLevel[] {
    if (!Array.isArray(value)) return []
    return value.filter(row => row && /^ML[1-9]\d*$/.test(String(row.code || ''))).map(row => {
        const benefits = (Array.isArray(row.benefits) ? row.benefits : []).filter(item => item && typeof item.key === 'string').map(item => ({key:String(item.key),title:String(item.title||''),desc:String(item.desc||''),icon:String(item.icon||''),enabled:enabled(item.enabled)}))
        const required=String(row.experience_required??'')
        const experience_required=/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/.test(required)?required.split('.')[0]+'.'+(required.split('.')[1]||'').padEnd(2,'0'):null
        const experience_eligible=typeof row.experience_eligible==='boolean'?row.experience_eligible:null
        const costs=normalizeSubjectBenefit(row)
        return { code: String(row.code), level_id: Number(row.level_id), name: String(row.name || ''), rank: Number(row.rank || 0), display_rank: Number(row.display_rank || 0), is_entry:enabled(row.is_entry), level_color:validLevelColor(row.level_color)?row.level_color:'', experience_required, experience_eligible, deposit_amount:costs.deposit_amount,experience_purchase_enabled:costs.experience_purchase_enabled,experience_unit_price:costs.experience_unit_price, account_limit: Number(row.account_limit), unlimited: enabled(row.unlimited), cycles: Array.isArray(row.cycles) ? normalizeSubjectBenefit({ cycles: row.cycles }).cycles : [], capabilities: Object.fromEntries(Object.keys(subjectCapabilityLabels).map(key=>[key,enabled(row.capabilities?.[key])])), benefits }
    })
}
export function entitySeatText(membership: Record<string, any> = {}): string {
    const used = Number(membership.used_accounts)
    if (!Number.isInteger(used) || used < 0) return '名额待核对'
    if (membership.unlimited === true) return `${used} 人 / 不限人数`
    const limit = Number(membership.account_limit)
    return Number.isInteger(limit) && limit >= 1 ? `${used} / ${limit} 人` : '名额待核对'
}
export function entityMembershipState(membership: Record<string, any> = {}) {
    const display = { active: { label: '生效中', tone: 'success' as const }, expired: { label: '已到期', tone: 'warning' as const }, review: { label: '待核对', tone: 'warning' as const }, inactive: { label: '协作已停用', tone: 'info' as const }, not_opened: { label: '未开通', tone: 'info' as const } }
    if (Object.prototype.hasOwnProperty.call(display, membership.display_status)) return display[membership.display_status as keyof typeof display]
    if (membership.review_required === true) return display.review
    if (membership.cooperation_exited === true) return display.inactive
    if (membership.active === true) return { label: '生效中', tone: 'success' as const }
    if (membership.state === 'expired' || Number(membership.expires_at) > 0) return { label: '已到期', tone: 'warning' as const }
    return { label: '未开通', tone: 'info' as const }
}
export function filterEntityMembers(rows: Record<string, any>[], keyword: string, status = '') {
    const key = keyword.trim().toLowerCase()
    return rows.filter(row => (!status || row.status === status) && (!key || [row.name, row.nickname, row.mobile, row.member_id].some(value => String(value || '').toLowerCase().includes(key))))
}
