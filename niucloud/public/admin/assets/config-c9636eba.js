import"./base-0e92f4db.js";/* empty css                   *//* empty css                  */import{a as D,E as F}from"./el-form-item-c2dd2ffe.js";/* empty css                *//* empty css                 *//* empty css                  */import{t as a}from"./index-2d1c7ba3.js";import{x as w,S as I}from"./index-fac59425.js";import{a3 as M}from"./event-a537c4cb.js";import{u as R}from"./vue-router-8b032575.js";import{a as v}from"./index-e9d9b1a1.js";import{E as j}from"./index-bffcf33f.js";import{E as H}from"./index-8cefa3ab.js";import{E as L}from"./index-2668a8ea.js";import{E as O}from"./index-e09a20f5.js";import{v as U}from"./directive-c6f70b8e.js";import{d as $,r as g,M as T,w as z,b as h,e as A,f as r,x as c,u as n,L as G,m as J,p as i,q as l,v as K}from"./runtime-core.esm-bundler-67034826.js";import{_ as P}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-72686045.js";import"./common-46715e7e.js";import"./index-81f2aa1e.js";import"./el-main-7a89c415.js";import"./index-ebd2990f.js";import"./el-overlay-3eff2fc5.js";import"./index-defed8ff.js";import"./focus-trap-83769a43.js";import"./index-6cae7119.js";import"./index-d87ae4a2.js";/* empty css                  */import"./index-ef31373f.js";import"./index-de22cd40.js";import"./validator-9409f909.js";function Q(){return w.get("channel/h5/config")}function W(u){return w.put("channel/h5/config",u,{showSuccessMessage:!0})}const X={class:"main-container"},Y={class:"flex ml-[18px] justify-between items-center mt-[20px]"},Z={class:"text-[20px]"},ee={class:"fixed-footer-wrap"},te={class:"fixed-footer"},oe=$({__name:"config",setup(u){const x=R().meta.title,s=g(!0),e=T({is_open:!1,request_url:""}),_=g();Q().then(t=>{Object.assign(e,t.data),e.is_open=Boolean(Number(e.is_open)),s.value=!1}),I().then(t=>{e.request_url=t.data.wap_url+"/"});const{copy:y,isSupported:b,copied:d}=M(),E=t=>{if(!b.value){v({message:a("notSupportCopy"),type:"warning"});return}y(t)};z(d,()=>{d.value&&v({message:a("copySuccess"),type:"success"})});const C=()=>{window.open(e.request_url)},k=async t=>{s.value||!t||await t.validate(async o=>{if(o){s.value=!0;let p={...e};p.is_open=Number(p.is_open),W(p).then(()=>{s.value=!1}).catch(()=>{s.value=!1})}})};return(t,o)=>{const p=j,f=D,q=H,N=L,S=F,V=O,B=U;return h(),A("div",X,[r("div",Y,[r("span",Z,c(n(x)),1)]),G((h(),J(S,{model:e,"label-width":"150px",ref_key:"formRef",ref:_,class:"page-form"},{default:i(()=>[l(N,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[l(f,{label:n(a)("isOpen")},{default:i(()=>[l(p,{modelValue:e.is_open,"onUpdate:modelValue":o[0]||(o[0]=m=>e.is_open=m)},null,8,["modelValue"])]),_:1},8,["label"]),l(f,{label:n(a)("h5DomainName")},{default:i(()=>[l(q,{"model-value":e.request_url,class:"input-width",readonly:!0},{append:i(()=>[r("div",{class:"cursor-pointer",onClick:o[1]||(o[1]=m=>E(e.request_url))},c(n(a)("copy")),1)]),_:1},8,["model-value"]),r("span",{class:"ml-2 cursor-pointer visit-btn",onClick:C},c(n(a)("clickVisit")),1)]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[B,s.value]]),r("div",ee,[r("div",te,[l(V,{type:"primary",loading:s.value,onClick:o[2]||(o[2]=m=>k(_.value))},{default:i(()=>[K(c(n(a)("save")),1)]),_:1},8,["loading"])])])])}}});const He=P(oe,[["__scopeId","data-v-6c14296d"]]);export{He as default};
