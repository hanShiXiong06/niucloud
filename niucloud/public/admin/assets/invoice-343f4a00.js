/* empty css             *//* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-d5447f06.js";/* empty css                  *//* empty css                        *//* empty css                    */import"./el-tooltip-4ed993c7.js";/* empty css                    *//* empty css                */import{a as j,E as H}from"./el-form-item-144bc604.js";/* empty css                  *//* empty css                       */import{t as a}from"./index-ebefdd26.js";import{j as R}from"./order-42c6f6ce.js";import{_ as S}from"./invoice-detail.vue_vue_type_script_setup_true_lang-d7d6b260.js";import{_ as q}from"./invoice-dialog.vue_vue_type_script_setup_true_lang-dbe0784a.js";import{u as O,a as A}from"./vue-router-9f815af7.js";import{E as G}from"./index-5f2625ed.js";import{E as J}from"./index-3da0204a.js";import{E as K}from"./index-6f670765.js";import{E as Q}from"./index-c5af06c2.js";import{a as W,E as X}from"./index-6982a78b.js";import{a as Z,E as ee}from"./index-6191b0b4.js";import{E as te}from"./index-cefc0b67.js";import{v as ae}from"./directive-d226d53f.js";import{d as oe,M as le,r as f,b as h,e as ie,q as e,p as r,f as u,x as m,u as t,v as d,L as re,m as x,C as ne}from"./runtime-core.esm-bundler-c17ab5bc.js";import"./common-a45d855f.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./event-3e082a4a.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";import"./_baseClone-37ba2e68.js";/* empty css                             */import"./index-7fa25c22.js";import"./index-76a877c2.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-b9e244b2.js";import"./attachment-2dcaf405.js";/* empty css                 *//* empty css                 *//* empty css               *//* empty css                    *//* empty css                   */import"./index-0ee6390f.js";import"./index-b74135ff.js";import"./aria-adfa05c5.js";import"./validator-f5e079db.js";import"./index-4ea26b0e.js";import"./index-d6b4c236.js";import"./index-6701860e.js";import"./index-f6eed9e8.js";import"./debounce-196ce6a6.js";import"./position-0d02b322.js";import"./index-d64b2f0e.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./index-b7b91fcb.js";import"./index-5c20ff8f.js";import"./strings-e72e60f7.js";import"./index-bfecf17f.js";import"./arrays-e667dc24.js";import"./index-43e913f7.js";import"./_isIterateeCall-797defa1.js";const me={class:"main-container"},pe={class:"flex justify-between items-center"},se={class:"text-[20px]"},de={class:"mt-[16px] flex justify-end"},Mt=oe({__name:"invoice",setup(ce){const w=O(),V=A(),P=w.meta.title,i=le({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{is_invoice:"",create_time:"",invoice_time:"",header_name:""}}),y=f(),T=n=>{n&&(n.resetFields(),s())},k=f(""),N=n=>{i.searchParam.is_invoice=n,s()},s=(n=1)=>{i.loading=!0,i.page=n,R({page:i.page,limit:i.limit,...i.searchParam}).then(o=>{i.loading=!1,i.data=o.data.data,i.total=o.data.total}).catch(()=>{i.loading=!1})};s();const v=f(null),$=n=>{v.value.setFormData(n),v.value.showDialog=!0},b=f(null),I=n=>{b.value.setInvoiceData(n),b.value.invoiceDialog=!0},Y=n=>{if(n.trade_type==="shop"){const o=V.resolve({path:"/shop/order/detail",query:{order_id:n.trade_id}});window.open(o.href,"_blank")}};return(n,o)=>{const F=G,_=j,D=J,c=K,U=H,C=Q,g=W,z=X,p=Z,L=ee,M=te,B=ae;return h(),ie("div",me,[e(C,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[u("div",pe,[u("span",se,m(t(P)),1)]),e(C,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:r(()=>[e(U,{inline:!0,model:i.searchParam,ref_key:"searchFormRef",ref:y},{default:r(()=>[e(_,{label:t(a)("headerName"),prop:"header_type"},{default:r(()=>[e(F,{modelValue:i.searchParam.header_name,"onUpdate:modelValue":o[0]||(o[0]=l=>i.searchParam.header_name=l),placeholder:t(a)("headerNamePlaceholder"),clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),e(_,{label:t(a)("createTime"),prop:"create_time"},{default:r(()=>[e(D,{modelValue:i.searchParam.create_time,"onUpdate:modelValue":o[1]||(o[1]=l=>i.searchParam.create_time=l),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":t(a)("startDate"),"end-placeholder":t(a)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),e(_,{label:t(a)("invoiceTime"),prop:"invoice_time"},{default:r(()=>[e(D,{modelValue:i.searchParam.create_time,"onUpdate:modelValue":o[2]||(o[2]=l=>i.searchParam.create_time=l),type:"datetimerange","value-format":"YYYY-MM-DD HH:mm:ss","start-placeholder":t(a)("startDate"),"end-placeholder":t(a)("endDate")},null,8,["modelValue","start-placeholder","end-placeholder"])]),_:1},8,["label"]),e(_,null,{default:r(()=>[e(c,{type:"primary",onClick:o[3]||(o[3]=l=>s())},{default:r(()=>[d(m(t(a)("search")),1)]),_:1}),e(c,{onClick:o[4]||(o[4]=l=>T(y.value))},{default:r(()=>[d(m(t(a)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),e(z,{modelValue:k.value,"onUpdate:modelValue":o[5]||(o[5]=l=>k.value=l),class:"demo-tabs",onTabChange:N},{default:r(()=>[e(g,{label:t(a)("all"),name:""},null,8,["label"]),e(g,{label:t(a)("已开票"),name:"1"},null,8,["label"]),e(g,{label:t(a)("未开票"),name:"0"},null,8,["label"])]),_:1},8,["modelValue"]),u("div",null,[re((h(),x(L,{data:i.data,size:"large"},{empty:r(()=>[u("span",null,m(i.loading?"":t(a)("emptyData")),1)]),default:r(()=>[e(p,{prop:"header_name",label:t(a)("headerName"),"min-width":"100",align:"left"},null,8,["label"]),e(p,{prop:"header_type_name",label:t(a)("headerTypeName"),"min-width":"120"},null,8,["label"]),e(p,{prop:"tax_number",label:t(a)("taxNumber"),"min-width":"180"},null,8,["label"]),e(p,{prop:"name",label:t(a)("name"),"min-width":"120"},null,8,["label"]),e(p,{prop:"money",label:t(a)("money"),"min-width":"120",align:"right"},null,8,["label"]),e(p,{label:t(a)("createTime"),"min-width":"180",align:"center"},{default:r(({row:l})=>[d(m(l.create_time||""),1)]),_:1},8,["label"]),e(p,{label:t(a)("invoiceTime"),"min-width":"180",align:"center"},{default:r(({row:l})=>[d(m(l.invoice_time||""),1)]),_:1},8,["label"]),e(p,{label:t(a)("isInvoice"),"min-width":"120",align:"center"},{default:r(({row:l})=>[d(m(l.is_invoice===1?t(a)("hasInvoice"):t(a)("noInvoice")),1)]),_:1},8,["label"]),e(p,{label:t(a)("operation"),fixed:"right",align:"center",width:"130"},{default:r(({row:l})=>[e(c,{type:"primary",link:"",onClick:E=>$(l)},{default:r(()=>[d(m(t(a)("detail")),1)]),_:2},1032,["onClick"]),l.is_invoice===0?(h(),x(c,{key:0,type:"primary",link:"",onClick:E=>I(l)},{default:r(()=>[d(m(t(a)("invoice")),1)]),_:2},1032,["onClick"])):ne("",!0),e(c,{type:"primary",link:"",onClick:E=>Y(l)},{default:r(()=>[d(m(t(a)("viewOrder")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[B,i.loading]]),u("div",de,[e(M,{"current-page":i.page,"onUpdate:currentPage":o[6]||(o[6]=l=>i.page=l),"page-size":i.limit,"onUpdate:pageSize":o[7]||(o[7]=l=>i.limit=l),layout:"total, sizes, prev, pager, next, jumper",total:i.total,onSizeChange:o[8]||(o[8]=l=>s()),onCurrentChange:s},null,8,["current-page","page-size","total"])]),e(S,{ref_key:"invoiceDetailDialog",ref:v,onComplete:o[9]||(o[9]=l=>s())},null,512),e(q,{ref_key:"invoiceListDialog",ref:b,onComplete:o[10]||(o[10]=l=>s())},null,512)])]),_:1})])}}});export{Mt as default};
