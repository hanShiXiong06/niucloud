import{d as F,r as v,R as S,V as z,e as d,f,g as r,B as i,u as e,y as a,x as l,i as j,Q as q,v as I,F as U,z as W,A as g}from"./base-06478700.js";/* empty css                   *//* empty css                *//* empty css                        *//* empty css                    *//* empty css               */import"./el-tooltip-58212670.js";import"./index-80fd3793.js";/* empty css                  *//* empty css                 *//* empty css                    */import{t}from"./index-81ed253c.js";import{e as M,f as O}from"./weapp-c2fec376.js";import{e as P}from"./notice-46796069.js";import{u as Q,a as $}from"./vue-router-d09a2c28.js";import{E as G}from"./index-c151cd58.js";import{a as H,E as J}from"./index-0d66b73c.js";import{E as K}from"./index-2fcd1254.js";import{E as X}from"./index-37a46bd5.js";import{a as Y,E as Z}from"./index-4bec4464.js";import{E as ee}from"./index-c2f001d3.js";import{E as te}from"./index-e10fccde.js";import{v as ae}from"./directive-cb2d3366.js";import{_ as oe}from"./_plugin-vue_export-helper-c27b6911.js";import"./common-92a35870.js";import"./event-10eba222.js";import"./index-adb89d14.js";import"./el-main-9a0960e7.js";import"./index-6b67c4ac.js";import"./el-overlay-42a687c6.js";import"./index-9fe5de95.js";import"./focus-trap-3e826cdc.js";import"./index-f27d6ce0.js";import"./index-818c0ce2.js";import"./el-form-item-314d006d.js";/* empty css                 */import"./index-b52d0f2a.js";import"./index-b68e8463.js";import"./index-2a269c7c.js";import"./index-e4abfaa5.js";import"./index-9ee9102c.js";import"./strings-fe930bc4.js";import"./index-40e21e72.js";import"./isEqual-42d4b10f.js";import"./_isIterateeCall-1dc0e2ff.js";import"./debounce-1db848fd.js";import"./index-5a0d60aa.js";const le={class:"main-container p-5"},ne={class:"flex justify-between items-center mb-[20px]"},se={class:"text-[20px]"},ie={class:"flex"},pe={class:"text-base"},re={class:"text-base"},me=F({__name:"template",setup(ce){const w=Q(),y=$(),x=w.meta.title;let m=v("/website/channel/weapp/message");const k=p=>{y.push({path:m.value})},s=S({loading:!0,data:[]}),u=(p=1)=>{s.loading=!0,M().then(o=>{s.loading=!1,s.data=o.data}).catch(()=>{s.loading=!1})};u();const E=(p=null)=>{const o=G.service({lock:!0,background:"rgba(0, 0, 0, 0)"});O({keys:p?[p.key]:[]}).then(()=>{u(),o.close()}).catch(()=>{o.close()})},C=p=>{const o=v({key:"",type:"",status:0});o.value.status=p.is_weapp?0:1,o.value.key=p.key,o.value.type="weapp",s.loading=!0,P(o.value).then(_=>{u()}).catch(()=>{s.loading=!1})};return(p,o)=>{const _=H,T=J,B=z("Warning"),N=K,V=X,c=Y,h=ee,A=Z,D=te,L=ae;return d(),f("div",le,[r("div",ne,[r("span",se,i(e(x)),1)]),a(T,{modelValue:e(m),"onUpdate:modelValue":o[0]||(o[0]=n=>j(m)?m.value=n:m=n),class:"demo-tabs",onTabChange:k},{default:l(()=>[a(_,{label:e(t)("weappAccessFlow"),name:"/website/channel/weapp"},null,8,["label"]),a(_,{label:e(t)("subscribeMessage"),name:"/website/channel/weapp/message"},null,8,["label"]),a(_,{label:e(t)("weappRelease"),name:"/website/channel/weapp/code"},null,8,["label"])]),_:1},8,["modelValue"]),a(D,{class:"box-card !border-none",shadow:"never"},{default:l(()=>[a(V,{class:"warm-prompt !my-[20px]",type:"info"},{default:l(()=>[r("div",ie,[a(N,{class:"mr-2 mt-[2px]",size:"18"},{default:l(()=>[a(B)]),_:1}),r("div",null,[r("p",pe,i(e(t)("operationTip"))+" 1、"+i(e(t)("operationTipOne")),1),r("p",re,"2、"+i(e(t)("operationTipTwo")),1)])])]),_:1}),r("div",null,[q((d(),I(A,{data:s.data,size:"large"},{empty:l(()=>[r("span",null,i(s.loading?"":e(t)("emptyData")),1)]),default:l(()=>[a(c,{prop:"name","show-overflow-tooltip":!0,label:e(t)("name"),"min-width":"150"},null,8,["label"]),a(c,{label:e(t)("response"),"min-width":"180"},{default:l(({row:n})=>[(d(!0),f(U,null,W(n.weapp.content,(b,R)=>(d(),f("div",{key:"a"+R,class:"text-left"},i(b.join(":")),1))),128))]),_:1},8,["label"]),a(c,{label:e(t)("isStart"),"min-width":"100",align:"center"},{default:l(({row:n})=>[g(i(n.is_weapp==1?e(t)("startUsing"):e(t)("statusDeactivate")),1)]),_:1},8,["label"]),a(c,{prop:"weapp_template_id",label:e(t)("serialNumber"),"min-width":"180"},null,8,["label"]),a(c,{label:e(t)("operation"),fixed:"right",align:"right",width:"200"},{default:l(({row:n})=>[a(h,{type:"primary",link:"",onClick:b=>C(n)},{default:l(()=>[g(i(n.is_weapp==1?e(t)("close"):e(t)("open")),1)]),_:2},1032,["onClick"]),a(h,{type:"primary",link:"",onClick:b=>E(n)},{default:l(()=>[g(i(e(t)("regain")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[L,s.loading]])])]),_:1})])}}});const nt=oe(me,[["__scopeId","data-v-eabfccee"]]);export{nt as default};
