/** Safe presentation projection only. Enabled businesses and destinations stay server-owned. */
export const businessIconNames=['store','customer','order','stock','team','wallet','member','invite','connection'] as const
export const businessIconModes=['css','original','library','image','none'] as const
const designs:Record<string,{shape:string;icon:string;color:string;original:string}>={
    purchase:{shape:'phone',icon:'stock',color:'#3b82c4',original:'stores'},new_phone:{shape:'new',icon:'member',color:'#a47a3f',original:'home'},rent:{shape:'rent',icon:'order',color:'#7d69ad',original:'deliveries'},exchange:{shape:'exchange',icon:'connection',color:'#508a81',original:'stores'},broadband:{shape:'wifi',icon:'connection',color:'#5484a0',original:'home'},repair:{shape:'repair',icon:'order',color:'#987250',original:'profile'},home_recycle:{shape:'recycle',icon:'store',color:'#5b8a65',original:'stores'},
}
const text=(value:unknown,max:number)=>typeof value==='string'?value.slice(0,max):''
function color(value:unknown,fallback:string){return typeof value==='string'&&/^(?:#[\da-f]{3,4}|#[\da-f]{6}|#[\da-f]{8}|rgba?\([\d\s.,%]+\)|transparent)$/i.test(value.trim())?value.trim():fallback}
export function businessImage(value:unknown){if(typeof value!=='string'||value.length>1000||/[\s\x00-\x1f'"\\<>{}();]/.test(value)||/(?:^|\/)\.\.(?:\/|$)/.test(value)||/%2e/i.test(value))return'';return /^(?:https?:\/\/[^/?#@]+(?:[/?#]|$)|\/?(?:uploads?|attachment|addon)\/)/i.test(value)?value:''}
export function presentBusinessEntries(rows:unknown,appearance:Record<string,any>={}){
    if(!Array.isArray(rows))return[]
    const overrides=appearance.items&&typeof appearance.items==='object'&&!Array.isArray(appearance.items)?appearance.items:{}
    return rows.filter(row=>row&&typeof row.key==='string'&&typeof row.name==='string').map(row=>{
        const base=designs[row.key]||{shape:'phone',icon:'order',color:'#607b96',original:'home'},item=overrides[row.key]&&typeof overrides[row.key]==='object'?overrides[row.key]:{}
        const mode=businessIconModes.includes(item.iconMode)?item.iconMode:'css',icon=businessIconNames.includes(item.icon)?item.icon:base.icon,badgeMode=['hot','new','custom'].includes(item.badgeMode)?item.badgeMode:'none'
        return{key:row.key,name:text(item.title,30).trim()||row.name,iconMode:mode,icon,shape:base.shape,image:businessImage(item.image),original:'addon/hsx_lead/diy/tabbar/'+base.original+'.png',iconColor:color(item.iconColor,color(appearance.iconColor,base.color)),iconBgColor:color(item.iconBgColor,'#f4f7fb'),titleColor:color(item.titleColor,color(appearance.textColor,'#334155')),badge:badgeMode==='hot'?'HOT':badgeMode==='new'?'NEW':badgeMode==='custom'?text(item.badgeText,8).trim():'',badgeColor:color(item.badgeColor,'#ffffff'),badgeBgColor:color(item.badgeBgColor,'#ef6754')}
    })
}
export function businessPresentationLayout(value:unknown){return ['list','slide'].includes(String(value))?String(value):'compact'}
export function businessPresentationSize(value:unknown,fallback=40,min=24,max=64){const number=Number(value);return Number.isFinite(number)?Math.min(max,Math.max(min,Math.round(number))):fallback}
