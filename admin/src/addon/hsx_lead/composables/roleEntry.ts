export type LeadRole='stores'|'customers'
const common={primaryColor:'#398aff',titleColor:'#243b5b',textColor:'#718199',subtleColor:'#94a3b5',iconColor:'#536e9d',borderColor:'#cddff7',buttonColor:'#398aff',buttonTextColor:'#ffffff',titleSize:19,textSize:14,contentPadding:16,rowGap:12,itemRadius:10,buttonRadius:22}
export function roleEntryDefaults(role:LeadRole='stores'):Record<string,any>{
    return{...common,role,title:role==='stores'?'找门店':'找客户',subtitle:role==='stores'?'发布客户需求，选门店交付':'查看交给本店的客户线索',buttonText:role==='stores'?'去找门店':'查看客户',buttonStyle:role==='stores'?'solid':'link',image:'',usePresetImages:1,showImage:1,showBorder:1,animate:1,layoutPreset:'classic',componentStartBgColor:'#ffffff',componentEndBgColor:role==='stores'?'#e2efff':'',componentGradientAngle:'120deg',componentBgUrl:'',componentBgAlpha:0,topRounded:10,bottomRounded:10,...(role==='customers'?{buttonColor:'#536e9d',buttonTextColor:'#536e9d',borderColor:'#e0e7f1'}:{})}
}
/** Only an effective view. Never fills nested overrides on opening a saved combination. */
export function comboRoleFallback(component:Record<string,any>,role:LeadRole){
    const prefix=role==='stores'?'store':'customer',defaults={...roleEntryDefaults(role),primaryColor:'',titleColor:'#202124',textColor:'#606266',subtleColor:'#909399',iconColor:'#333333',borderColor:'#e4e8ef',buttonColor:'#398aff',buttonTextColor:role==='customers'?'#398aff':'#fff',titleSize:20,buttonRadius:6,itemRadius:8,componentStartBgColor:'',componentEndBgColor:'',componentGradientAngle:'to bottom',topRounded:undefined,bottomRounded:undefined},inherited:Record<string,any>={}
    for(const key of [...Object.keys(common),'layoutPreset','animate','usePresetImages'])if(component[key]!==undefined)inherited[key]=component[key]
    for(const [target,source]of Object.entries({title:prefix+'Title',subtitle:prefix+'Subtitle',buttonText:prefix+'ActionText',image:prefix+'Image'}))if(component[source]!==undefined)inherited[target]=component[source]
    return{...defaults,...inherited}
}
export function roleEntryError(value:Record<string,any>):string{
    if(!['stores','customers'].includes(value.role))return'请选择找门店或找客户角色'
    if(!String(value.title||'').trim())return'请填写入口标题'
    if(!['solid','link'].includes(value.buttonStyle))return'请选择有效的按钮样式'
    if(!['classic','compact','clean','soft'].includes(value.layoutPreset))return'请选择有效的卡片排版'
    return''
}
