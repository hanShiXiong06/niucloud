import"./base-962c0c23.js";/* empty css                        */import{_ as L}from"./index.vue_vue_type_style_index_0_lang-d0d31f1b.js";import{B as P}from"./index-7525671c.js";import{d as x}from"./storage-abe718b1.js";import{t as S}from"./index-105fbad0.js";import{a as Z,E as q}from"./index-190f0dba.js";import{d as M,c as j,M as v,w as R,b as o,e as n,f as l,h as g,u as s,q as i,m as $,p as B,x as E,F as y,t as A,C as N,af as D}from"./runtime-core.esm-bundler-dc7a07d7.js";import{_ as G}from"./_plugin-vue_export-helper-c27b6911.js";const H={class:"flex flex-wrap"},J={key:0,class:"w-full h-full relative"},K={class:"w-full h-full flex items-center justify-center"},O={class:"absolute z-[1] flex items-center justify-center w-full h-full inset-0 bg-black bg-opacity-60 operation"},Q={class:"w-full h-full flex items-center justify-center flex-col"},U={class:"leading-none text-xs mt-[10px] text-secondary"},W={class:"w-full h-full relative"},X={class:"w-full h-full flex items-center justify-center"},Y={class:"absolute z-[1] flex items-center justify-center w-full h-full inset-0 bg-black bg-opacity-60 operation"},ee={class:"w-full h-full flex items-center justify-center flex-col"},te={class:"leading-none text-xs mt-[10px] text-secondary"},le=M({__name:"index",props:{modelValue:{type:String,default:""},width:{type:String,default:"100px"},height:{type:String,default:"100px"},imageText:{type:String},limit:{type:Number,default:1}},emits:["update:modelValue","change"],setup(a,{emit:w}){const d=a,u=j({get(){return d.modelValue},set(e){w("update:modelValue",e)}}),t=v({data:[]});let b=v([]);const f=()=>{u.value=D(t.data).toString(),b=D(t.data).map(e=>x(e))};R(()=>u.value,()=>{t.data=[...u.value.split(",").filter(e=>e)],f()},{immediate:!0});const p=j(()=>({width:d.width,height:d.height})),k=e=>{d.limit==1?(t.data.splice(0,1),e&&t.data.push(e.url)):e.forEach(r=>{t.data.length<d.limit&&t.data.push(r.url)}),f(),w("change",u.value)},C=(e=0)=>{t.data.splice(e,1),f()},m=v({show:!1,index:0}),z=(e=0)=>{m.show=!0,m.index=e};return(e,r)=>{const V=q,c=P,I=L,T=Z;return o(),n(y,null,[l("div",H,[a.limit==1?(o(),n("div",{key:0,class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color image-wrap mr-[10px]",style:g(s(p))},[t.data.length?(o(),n("div",J,[l("div",K,[i(V,{src:s(x)(t.data[0]),fit:"contain"},null,8,["src"])]),l("div",O,[i(c,{name:"element-ZoomIn",color:"#fff",size:"18px",class:"mr-[10px]",onClick:r[0]||(r[0]=_=>z())}),i(c,{name:"element-Delete",color:"#fff",size:"18px",onClick:C})])])):(o(),$(I,{key:1,limit:a.limit,onConfirm:k},{default:B(()=>[l("div",Q,[i(c,{name:"element-Plus",size:"20px",color:"var(--el-text-color-secondary)"}),l("div",U,E(a.imageText||s(S)("upload.root")),1)])]),_:1},8,["limit"]))],4)):(o(),n(y,{key:1},[(o(!0),n(y,null,A(t.data,(_,h)=>(o(),n("div",{class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color image-wrap mr-[10px]",style:g(s(p)),key:h},[l("div",W,[l("div",X,[i(V,{src:s(x)(_),fit:"contain"},null,8,["src"])]),l("div",Y,[i(c,{name:"element-ZoomIn",color:"#fff",size:"18px",class:"mr-[10px]",onClick:F=>z(h)},null,8,["onClick"]),i(c,{name:"element-Delete",color:"#fff",size:"18px",onClick:F=>C(h)},null,8,["onClick"])])])],4))),128)),t.data.length<a.limit?(o(),n("div",{key:0,class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color",style:g(s(p))},[i(I,{limit:a.limit,onConfirm:k},{default:B(()=>[l("div",ee,[i(c,{name:"element-Plus",size:"20px",color:"var(--el-text-color-secondary)"}),l("div",te,E(a.imageText||s(S)("upload.root")),1)])]),_:1},8,["limit"])],4)):N("",!0)],64))]),m.show?(o(),$(T,{key:0,"url-list":s(b),onClose:r[1]||(r[1]=_=>m.show=!1),"initial-index":m.index,"zoom-rate":1},null,8,["url-list","initial-index"])):N("",!0)],64)}}});const ue=G(le,[["__scopeId","data-v-acbeec29"]]);export{ue as _};
