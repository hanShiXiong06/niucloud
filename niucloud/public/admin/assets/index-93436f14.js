import{_ as S}from"./index.vue_vue_type_style_index_0_lang-027a5d0f.js";import{_ as V}from"./index-1bbcede8.js";import{t as g}from"./index-3862e13d.js";import{d as j,c as w,M as B,w as N,b as o,e as s,h as m,u as c,f as l,q as i,m as D,p as b,x as k,F as z,t as T,C as $,ac as E}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as F}from"./_plugin-vue_export-helper-c27b6911.js";const I={class:"flex flex-wrap"},P={key:0,class:"w-full h-full relative"},q={class:"w-full h-full flex items-center justify-center"},L={class:"absolute z-[1] flex items-center justify-center w-full h-full inset-0 bg-black bg-opacity-60 operation"},M={class:"w-full h-full flex items-center justify-center flex-col"},R={class:"leading-none text-xs mt-[10px] text-secondary"},A={class:"w-full h-full relative"},G={class:"w-full h-full flex items-center justify-center"},H={class:"absolute z-[1] flex items-center justify-center w-full h-full inset-0 bg-black bg-opacity-60 operation"},J={class:"w-full h-full flex items-center justify-center flex-col"},K={class:"leading-none text-xs mt-[10px] text-secondary"},O=j({__name:"index",props:{modelValue:{type:String,default:""},width:{type:String,default:"100px"},height:{type:String,default:"100px"},iconText:{type:String},limit:{type:Number,default:1}},emits:["update:modelValue","change"],setup(a,{emit:p}){const r=a,d=w({get(){return r.modelValue},set(t){p("update:modelValue",t)}}),e=B({data:[]}),u=()=>{d.value=E(e.data).toString()};N(()=>d.value,()=>{e.data=[...d.value.split(",").filter(t=>t)],u()},{immediate:!0});const f=w(()=>({width:r.width,height:r.height})),_=t=>{r.limit==1?(e.data.splice(0,1),t&&e.data.push(t.url)):t.forEach(x=>{e.data.length<r.limit&&e.data.push(x.url)}),u(),p("change",d.value)},h=(t=0)=>{e.data.splice(t,1),u()};return(t,x)=>{const n=V,v=S;return o(),s("div",I,[a.limit==1?(o(),s("div",{key:0,class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color icon-wrap mr-[10px]",style:m(c(f))},[e.data.length?(o(),s("div",P,[l("div",q,[i(n,{name:e.data[0],size:"40px"},null,8,["name"])]),l("div",L,[i(n,{name:"element-Delete",color:"#fff",size:"18px",onClick:h})])])):(o(),D(v,{key:1,limit:a.limit,type:"icon",onConfirm:_},{default:b(()=>[l("div",M,[i(n,{name:"element-Plus",size:"20px",color:"var(--el-text-color-secondary)"}),l("div",R,k(a.iconText||c(g)("upload.selecticon")),1)])]),_:1},8,["limit"]))],4)):(o(),s(z,{key:1},[(o(!0),s(z,null,T(e.data,(C,y)=>(o(),s("div",{class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color icon-wrap mr-[10px]",style:m(c(f)),key:y},[l("div",A,[l("div",G,[i(n,{name:C,size:"40px"},null,8,["name"])]),l("div",H,[i(n,{name:"element-Delete",color:"#fff",size:"18px",onClick:Q=>h(y)},null,8,["onClick"])])])],4))),128)),e.data.length<a.limit?(o(),s("div",{key:0,class:"rounded cursor-pointer overflow-hidden relative border border-dashed border-color",style:m(c(f))},[i(v,{limit:a.limit,onConfirm:_},{default:b(()=>[l("div",J,[i(n,{name:"element-Plus",size:"20px",color:"var(--el-text-color-secondary)"}),l("div",K,k(a.iconText||c(g)("upload.selecticon")),1)])]),_:1},8,["limit"])],4)):$("",!0)],64))])}}});const ee=F(O,[["__scopeId","data-v-f94fa0a4"]]);export{ee as _};
