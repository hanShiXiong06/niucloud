/* empty css             *//* empty css                        */import{_ as L}from"./index.vue_vue_type_style_index_0_lang-52a2f80c.js";import{_ as O}from"./index-596de8a9.js";/* empty css                 */import{c as h}from"./common-a45d855f.js";import{t as S}from"./index-6b4cbbe4.js";import{E as P}from"./index-f6eed9e8.js";import{E as Z}from"./index-6701860e.js";import{d as q,c as j,M as v,w as M,b as o,e as n,f as l,h as g,u as i,q as a,m as E,p as $,x as B,F as y,t as R,C as N,ac as D}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as A}from"./_plugin-vue_export-helper-c27b6911.js";const G={class:"flex flex-wrap"},H={key:0,class:"w-full h-full relative"},J={class:"w-full h-full flex items-center justify-center"},K={class:"absolute z-[1] flex items-center justify-center w-full h-full inset-0 bg-black bg-opacity-60 operation"},Q={class:"w-full h-full flex items-center justify-center flex-col"},U={class:"leading-none text-xs mt-[10px] text-secondary"},W={class:"w-full h-full relative"},X={class:"w-full h-full flex items-center justify-center"},Y={class:"absolute z-[1] flex items-center justify-center w-full h-full inset-0 bg-black bg-opacity-60 operation"},ee={class:"w-full h-full flex items-center justify-center flex-col"},te={class:"leading-none text-xs mt-[10px] text-secondary"},le=q({__name:"index",props:{modelValue:{type:String,default:""},width:{type:String,default:"100px"},height:{type:String,default:"100px"},imageText:{type:String},limit:{type:Number,default:1}},emits:["update:modelValue","change"],setup(s,{emit:w}){const m=s,r=j({get(){return m.modelValue},set(t){w("update:modelValue",t)}}),e=v({data:[]});let b=v([]);const f=()=>{r.value=D(e.data).toString(),b=D(e.data).map(t=>h(t))};M(()=>r.value,()=>{r.value.indexOf("data:image")!=-1?e.data[0]=r.value+"":e.data=[...r.value.split(",").filter(t=>t)],f()},{immediate:!0});const p=j(()=>({width:m.width,height:m.height})),k=t=>{m.limit==1?(e.data.splice(0,1),t&&e.data.push(t.url)):t.forEach(c=>{e.data.length<m.limit&&e.data.push(c.url)}),f(),w("change",r.value)},C=(t=0)=>{e.data.splice(t,1),f()},u=v({show:!1,index:0}),z=(t=0)=>{u.show=!0,u.index=t};return(t,c)=>{const V=Z,d=O,I=L,T=P;return o(),n(y,null,[l("div",G,[s.limit==1?(o(),n("div",{key:0,class:"cursor-pointer overflow-hidden relative border border-solid border-color image-wrap mr-[10px]",style:g(i(p))},[e.data.length?(o(),n("div",H,[l("div",J,[a(V,{src:e.data[0].indexOf("data:image")!=-1?e.data[0]:i(h)(e.data[0]),fit:"contain"},null,8,["src"])]),l("div",K,[a(d,{name:"element-ZoomIn",color:"#fff",size:"18px",class:"mr-[10px]",onClick:c[0]||(c[0]=_=>z())}),a(d,{name:"element-Delete",color:"#fff",size:"18px",onClick:C})])])):(o(),E(I,{key:1,limit:s.limit,onConfirm:k},{default:$(()=>[l("div",Q,[a(d,{name:"element-Plus",size:"20px",color:"var(--el-text-color-secondary)"}),l("div",U,B(s.imageText||i(S)("upload.root")),1)])]),_:1},8,["limit"]))],4)):(o(),n(y,{key:1},[(o(!0),n(y,null,R(e.data,(_,x)=>(o(),n("div",{class:"cursor-pointer overflow-hidden relative border border-solid border-color image-wrap mr-[10px]",style:g(i(p)),key:x},[l("div",W,[l("div",X,[a(V,{src:i(h)(_),fit:"contain"},null,8,["src"])]),l("div",Y,[a(d,{name:"element-ZoomIn",color:"#fff",size:"18px",class:"mr-[10px]",onClick:F=>z(x)},null,8,["onClick"]),a(d,{name:"element-Delete",color:"#fff",size:"18px",onClick:F=>C(x)},null,8,["onClick"])])])],4))),128)),e.data.length<s.limit?(o(),n("div",{key:0,class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color",style:g(i(p))},[a(I,{limit:s.limit,onConfirm:k},{default:$(()=>[l("div",ee,[a(d,{name:"element-Plus",size:"20px",color:"var(--el-text-color-secondary)"}),l("div",te,B(s.imageText||i(S)("upload.root")),1)])]),_:1},8,["limit"])],4)):N("",!0)],64))]),u.show?(o(),E(T,{key:0,"url-list":i(b),onClose:c[1]||(c[1]=_=>u.show=!1),"initial-index":u.index,"zoom-rate":1},null,8,["url-list","initial-index"])):N("",!0)],64)}}});const pe=A(le,[["__scopeId","data-v-e97a6acd"]]);export{pe as _};
