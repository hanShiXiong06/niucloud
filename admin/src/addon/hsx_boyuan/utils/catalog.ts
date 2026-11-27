import type { HsxTableColumn } from '@/addon/hsx_components/types'
export const directory = [
    {label:'业务往来',items:[['parties','客户与供应商'],['members','会员']]},
    {label:'商品资料',items:[['products','商品型号'],['categories','商品分类'],['brands','品牌']]},
    {label:'组织与仓储',items:[['companies','公司门店'],['warehouses','仓库'],['staff','业务人员']]},
    {label:'财务资料',items:[['payment_methods','结算方式'],['capital_accounts','资金账户']]}
]
export const titleFor=(key:string)=>directory.flatMap(g=>g.items).find(i=>i[0]===key)?.[1] || ''
const flag=(v:unknown)=>[1,'1',true].includes(v as any)
const yesNo=(v:unknown)=>flag(v)?'是':[false,'0',0].includes(v as any)?'否':'—'
const field=(prop:string,label:string,minWidth=100):HsxTableColumn<any>=>({prop:'extra.'+prop,label,minWidth,formatter:(_,v)=>v??'—'})
const bool=(prop:string,label:string):HsxTableColumn<any>=>({...field(prop,label,95),formatter:(_,v)=>yesNo(v)})
const phone:HsxTableColumn<any>={prop:'phone_masked',label:'联系电话',minWidth:130,formatter:(r,v)=>r.phone_state==='refresh'?'待按当前规则取回':r.phone_state==='check'?'原值待核对':v || '—'}
const owner:HsxTableColumn<any>={...field('company_name','所属公司',140),formatter:(r,v)=>v || r.company?.name || r.extra?.company_id || r.extra?.gsdm || '—'}
export const roleText=(r:any)=>[flag(r.extra?.is_cust)?'客户':'',flag(r.extra?.is_supplier)?'供应商':''].filter(Boolean).join(' / ') || '未标注'
const role:HsxTableColumn<any>={prop:'role',label:'往来身份',minWidth:114,formatter:roleText}
const code:HsxTableColumn<any>={prop:'code',label:'档案编码',minWidth:100}
const controls:Record<string,HsxTableColumn<any>[]>={
    parties:[role,{prop:'phone_number',label:'联系电话',minWidth:150,slot:'partyPhone'},field('sales_name','业务员')], members:[phone,owner,field('register_date','登记日期')],
    products:[field('brand_name','品牌'),field('category_name','分类'),field('memory','内存'),field('color','颜色')],
    categories:[code,field('parent_name','上级分类')],brands:[code,field('spell_code','拼音码')],
    companies:[field('gsdm','公司代码'),field('parent_name','上级公司'),bool('is_store','是否门店')],
    warehouses:[owner,field('warehouse_type_name','仓库类型'),bool('is_allow_sales','允许销售')],
    staff:[owner,bool('is_buyer','采购员'),bool('is_sales','业务员')],
    payment_methods:[field('cash_type','收银分类'),{...field('inout_tag','结算方向'),formatter:(_,v)=>String(v)==='1'?'应收':String(v)==='-1'?'应付':v??'—'},field('account_name','默认账户')],
    capital_accounts:[owner,field('account_type','账户类型'),field('gsdm','公司代码')]
}
const captions:Record<string,string>={parties:'往来单位',members:'会员姓名',products:'商品型号',categories:'分类名称',brands:'品牌名称',companies:'公司 / 门店',warehouses:'仓库名称',staff:'人员姓名',payment_methods:'结算方式',capital_accounts:'账户名称'}
export function catalogColumns(kind:string):HsxTableColumn<any>[] {
    return [{prop:'name',label:captions[kind] || '名称',slot:'record',minWidth:168},...(controls[kind] || []),{prop:'enabled',label:'状态',slot:'status',width:102}]
}
export function archiveStatus(row:any){return row.extra?.status_known===false?'未提供':row.enabled?'启用':'停用'}
export function emptyReason(meta:any,filtered:boolean) {
    if(filtered)return '没有符合筛选条件的档案'
    if(!meta?.job && !meta?.count)return '尚未同步这类资料'
    return meta?.job?.status==='completed'?'博远本次返回 0 条档案':'本站还没有收到这类档案'
}
