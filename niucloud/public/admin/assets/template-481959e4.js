import{E as B}from"./base-962c0c23.js";/* empty css                   *//* empty css                *//* empty css                 *//* empty css                    *//* empty css               */import"./el-tooltip-58212670.js";import"./index-7525671c.js";/* empty css                        *//* empty css                 *//* empty css                *//* empty css                  */import{t as e}from"./index-105fbad0.js";import{a as L,b as N}from"./weapp-494503b1.js";import{e as D}from"./notice-ab352737.js";import{u as q}from"./vue-router-79053937.js";import{E as A}from"./index-71b8cde8.js";import{E as S}from"./index-bba9e58c.js";import{E as F}from"./index-d1bcad42.js";import{a as V,E as j}from"./index-92e1b5d5.js";import{E as z}from"./index-69523418.js";import{v as W}from"./directive-c0c3e9a3.js";import{d as $,M as I,Q as M,b as d,e as b,q as o,p as a,f as r,x as n,u as t,v as _,L as O,m as Q,F as R,t as U,r as G}from"./runtime-core.esm-bundler-dc7a07d7.js";import"./el-overlay-60700377.js";import"./event-ff03ec12.js";import"./index-5d86eb33.js";import"./focus-trap-b8b5a003.js";/* empty css                 */import"./el-radio-bfd4b1ad.js";import"./storage-abe718b1.js";import"./index-8bcaafa6.js";import"./index-93f2c618.js";import"./index-7a123a20.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-avatar-3bb47ce2.js";import"./index-d57cc47d.js";import"./common-080b9b9f.js";import"./common-2cf17469.js";import"./_Uint8Array-6ff3cafa.js";import"./_initCloneObject-28e6bdaa.js";import"./index-df51d91a.js";import"./isEqual-c7d5e6d9.js";import"./flatten-d5d1dc97.js";import"./_isIterateeCall-c579b126.js";const H={class:"main-container"},J={class:"flex justify-between items-center"},K={class:"text-[24px]"},P={class:"flex"},X={class:"text-base"},Y={class:"text-base"},Ut=$({__name:"template",setup(Z){const h=q().meta.title,s=I({loading:!0,data:[]}),u=(l=1)=>{s.loading=!0,L().then(i=>{s.loading=!1,s.data=i.data}).catch(()=>{s.loading=!1})};u();const g=(l=null)=>{const i=A.service({lock:!0,background:"rgba(0, 0, 0, 0)"});N({keys:l?[l.key]:[]}).then(()=>{u(),i.close()}).catch(()=>{i.close()})},v=l=>{const i=G({key:"",type:"",status:0});i.value.status=l.is_weapp?0:1,i.value.key=l.key,i.value.type="weapp",s.loading=!0,D(i.value).then(c=>{u()}).catch(()=>{s.loading=!1})};return(l,i)=>{const c=S,y=M("Warning"),k=B,w=F,m=V,x=j,E=z,C=W;return d(),b("div",H,[o(E,{class:"box-card !border-none",shadow:"never"},{default:a(()=>[r("div",J,[r("span",K,n(t(h)),1),o(c,{type:"primary",class:"w-[100px]",onClick:g},{default:a(()=>[_(n(t(e)("batchAcquisition")),1)]),_:1})]),o(w,{class:"warm-prompt !my-[20px]",type:"info"},{default:a(()=>[r("div",P,[o(k,{class:"mr-2 mt-[2px]",size:"18"},{default:a(()=>[o(y)]),_:1}),r("div",null,[r("p",X,n(t(e)("operationTip"))+" 1、"+n(t(e)("operationTipOne")),1),r("p",Y,"2、"+n(t(e)("operationTipTwo")),1)])])]),_:1}),r("div",null,[O((d(),Q(x,{data:s.data,size:"large"},{empty:a(()=>[r("span",null,n(s.loading?"":t(e)("emptyData")),1)]),default:a(()=>[o(m,{prop:"name","show-overflow-tooltip":!0,label:t(e)("name"),"min-width":"150"},null,8,["label"]),o(m,{label:t(e)("response"),"min-width":"180"},{default:a(({row:p})=>[(d(!0),b(R,null,U(p.weapp.content,(f,T)=>(d(),b("div",{key:"a"+T,class:"text-left"},n(f.join(":")),1))),128))]),_:1},8,["label"]),o(m,{label:t(e)("isStart"),"min-width":"100",align:"center"},{default:a(({row:p})=>[_(n(p.is_weapp==1?t(e)("startUsing"):t(e)("statusDeactivate")),1)]),_:1},8,["label"]),o(m,{prop:"weapp_template_id",label:t(e)("serialNumber"),"min-width":"180"},null,8,["label"]),o(m,{label:t(e)("operation"),fixed:"right",width:"200"},{default:a(({row:p})=>[o(c,{type:"primary",link:"",onClick:f=>v(p)},{default:a(()=>[_(n(p.is_weapp==1?t(e)("close"):t(e)("open")),1)]),_:2},1032,["onClick"]),o(c,{type:"danger",link:"",onClick:f=>g(p)},{default:a(()=>[_(n(t(e)("regain")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[C,s.loading]])])]),_:1})])}}});export{Ut as default};