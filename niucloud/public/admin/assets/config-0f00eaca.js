import{g as S,r as v,a4 as F,w as I,m as g,n as R,q as n,L as c,u as r,a1 as j,D as H,E as i,F as l,K as L}from"./base-45eb5090.js";/* empty css                   *//* empty css                  *//* empty css                     *//* empty css                *//* empty css                 */import{D as w,a0 as M,s as O}from"./index-ad71a852.js";import{t as a}from"./index-3694a2b2.js";import{J as U}from"./event-4977bef7.js";import{u as $}from"./vue-router-fcbaaf02.js";import{a as h}from"./index-aef37b98.js";import{a as J,E as K}from"./index-2bfbe5a7.js";import{E as T}from"./index-4ce9333e.js";import{E as z}from"./index-fc3020f4.js";import{E as A}from"./index-25c37860.js";import{v as G}from"./directive-9f485fe5.js";import{_ as P}from"./_plugin-vue_export-helper-c27b6911.js";import"./el-overlay-616d6124.js";import"./index-cd1661d3.js";import"./focus-trap-318ae2e0.js";import"./el-radio-2719e44c.js";import"./storage-4159d1ed.js";import"./index-9670e877.js";import"./index-3be486d3.js";import"./el-tooltip-58212670.js";import"./el-avatar-bc58ad46.js";import"./common-af78c857.js";import"./common-2cf17469.js";import"./_Uint8Array-e584e472.js";import"./_initCloneObject-983ff8c8.js";function Q(){return w.get("channel/h5/config")}function W(u){return w.put("channel/h5/config",u,{showSuccessMessage:!0})}const X={class:"main-container"},Y={class:"flex ml-[18px] justify-between items-center mt-[20px]"},Z={class:"text-[20px]"},ee={class:"fixed-footer-wrap"},te={class:"fixed-footer"},oe=S({__name:"config",setup(u){const y=$().meta.title,s=v(!0),e=F({is_open:!1,request_url:""}),_=v();Q().then(t=>{Object.assign(e,t.data),e.is_open=Boolean(Number(e.is_open)),s.value=!1}),M().then(t=>{e.request_url=t.data.wap_url+"/"});const{copy:b,isSupported:x,copied:d}=U(),E=t=>{if(!x.value){h({message:a("notSupportCopy"),type:"warning"});return}b(t)};I(d,()=>{d.value&&h({message:a("copySuccess"),type:"success"})});const C=()=>{window.open(e.request_url)},k=async t=>{s.value||!t||await t.validate(async o=>{if(o){s.value=!0;let p={...e};p.is_open=Number(p.is_open),W(p).then(()=>{s.value=!1}).catch(()=>{s.value=!1})}})};return(t,o)=>{const p=O,f=J,q=T,N=z,V=K,B=A,D=G;return g(),R("div",X,[n("div",Y,[n("span",Z,c(r(y)),1)]),j((g(),H(V,{model:e,"label-width":"150px",ref_key:"formRef",ref:_,class:"page-form"},{default:i(()=>[l(N,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[l(f,{label:r(a)("isOpen")},{default:i(()=>[l(p,{modelValue:e.is_open,"onUpdate:modelValue":o[0]||(o[0]=m=>e.is_open=m)},null,8,["modelValue"])]),_:1},8,["label"]),l(f,{label:r(a)("h5DomainName")},{default:i(()=>[l(q,{"model-value":e.request_url,class:"input-width",readonly:!0},{append:i(()=>[n("div",{class:"cursor-pointer",onClick:o[1]||(o[1]=m=>E(e.request_url))},c(r(a)("copy")),1)]),_:1},8,["model-value"]),n("span",{class:"ml-2 cursor-pointer visit-btn",onClick:C},c(r(a)("clickVisit")),1)]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[D,s.value]]),n("div",ee,[n("div",te,[l(B,{type:"primary",loading:s.value,onClick:o[2]||(o[2]=m=>k(_.value))},{default:i(()=>[L(c(r(a)("save")),1)]),_:1},8,["loading"])])])])}}});const Re=P(oe,[["__scopeId","data-v-2bd49fdf"]]);export{Re as default};