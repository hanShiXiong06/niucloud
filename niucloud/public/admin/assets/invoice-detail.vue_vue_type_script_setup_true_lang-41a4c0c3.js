/* empty css             *//* empty css                   */import{E as N}from"./el-overlay-380df695.js";/* empty css                  */import"./index-596de8a9.js";/* empty css                             */import{t as a}from"./index-6b4cbbe4.js";import{a as V}from"./order-ccbb364d.js";import{a as w,E as C}from"./index-7fa25c22.js";import{E as B}from"./index-72bf6cb5.js";import{E as T}from"./index-6f670765.js";import{v as I}from"./directive-d226d53f.js";import{d as L,r as _,b as c,m as v,p as e,f as p,q as t,v as n,x as r,u as i,L as F,e as S,C as q}from"./runtime-core.esm-bundler-c17ab5bc.js";const U=["src"],$={class:"dialog-footer"},Z=L({__name:"invoice-detail",setup(j,{expose:f}){const s=_(!1),m=_(!1),l=_([]),h=async()=>{l.value=await(await V(d)).data,m.value=!1};let d=0;return f({showDialog:s,setFormData:async(b=null)=>{m.value=!0,b&&(d=b.id,h())}}),(b,u)=>{const o=w,k=C,x=B,y=T,D=N,E=I;return c(),v(D,{modelValue:s.value,"onUpdate:modelValue":u[1]||(u[1]=g=>s.value=g),title:i(a)("detail"),width:"800px","destroy-on-close":!0},{footer:e(()=>[p("span",$,[t(y,{onClick:u[0]||(u[0]=g=>s.value=!1)},{default:e(()=>[n(r(i(a)("cancel")),1)]),_:1})])]),default:e(()=>[F((c(),v(x,{height:"400px"},{default:e(()=>[t(k,{column:2},{default:e(()=>[t(o,{label:i(a)("headerName"),"label-align":"right"},{default:e(()=>[n(r(l.value.header_name),1)]),_:1},8,["label"]),t(o,{label:i(a)("headTypeName"),"label-align":"right"},{default:e(()=>[n(r(l.value.header_type_name),1)]),_:1},8,["label"]),t(o,{label:i(a)("name"),"label-align":"right"},{default:e(()=>[n(r(l.value.name),1)]),_:1},8,["label"]),t(o,{label:i(a)("invoiceNumber"),"label-align":"right"},{default:e(()=>[n(r(l.value.invoice_number),1)]),_:1},8,["label"]),t(o,{label:i(a)("typeName"),"label-align":"right"},{default:e(()=>[n(r(l.value.type_name),1)]),_:1},8,["label"]),t(o,{label:i(a)("taxNumber"),"label-align":"right"},{default:e(()=>[n(r(l.value.tax_number),1)]),_:1},8,["label"]),t(o,{label:i(a)("money"),"label-align":"right"},{default:e(()=>[n(r(l.value.money),1)]),_:1},8,["label"]),t(o,{label:i(a)("invoiceTime"),"label-align":"right"},{default:e(()=>[n(r(l.value.invoice_time===0?"":l.value.invoice_time),1)]),_:1},8,["label"]),t(o,{label:i(a)("invoiceVoucher"),"label-align":"right"},{default:e(()=>[p("span",null,[l.value.invoice_voucher?(c(),S("img",{key:0,class:"w-[50px] h-[50px] inline-block",src:l.value.invoice_voucher,alt:""},null,8,U)):q("",!0)])]),_:1},8,["label"]),t(o,{label:i(a)("bankTame"),"label-align":"right"},{default:e(()=>[n(r(l.value.bank_name),1)]),_:1},8,["label"]),t(o,{label:i(a)("bankCardNumber"),"label-align":"right"},{default:e(()=>[n(r(l.value.bank_card_number),1)]),_:1},8,["label"]),t(o,{label:i(a)("address"),"label-align":"right"},{default:e(()=>[n(r(l.value.address),1)]),_:1},8,["label"]),t(o,{label:i(a)("telephone"),"label-align":"right"},{default:e(()=>[n(r(l.value.telephone),1)]),_:1},8,["label"]),t(o,{label:i(a)("email"),"label-align":"right"},{default:e(()=>[n(r(l.value.email),1)]),_:1},8,["label"]),t(o,{label:i(a)("mobile"),"label-align":"right"},{default:e(()=>[n(r(l.value.mobile),1)]),_:1},8,["label"]),t(o,{label:i(a)("createTime"),"label-align":"right"},{default:e(()=>[n(r(l.value.create_time),1)]),_:1},8,["label"]),t(o,{label:i(a)("remark"),"label-align":"right"},{default:e(()=>[n(r(l.value.remark),1)]),_:1},8,["label"])]),_:1})]),_:1})),[[E,m.value]])]),_:1},8,["modelValue","title"])}}});export{Z as _};
