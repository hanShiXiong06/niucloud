import"./base-0e92f4db.js";/* empty css                   */import{a as S,E as k}from"./el-form-item-c2dd2ffe.js";/* empty css                *//* empty css                 */import{t as e}from"./index-2d1c7ba3.js";import{S as q}from"./index-fac59425.js";import{a3 as I}from"./event-a537c4cb.js";import{u as D,a as N}from"./vue-router-8b032575.js";import{a as u}from"./index-e9d9b1a1.js";import{E as R}from"./index-8cefa3ab.js";import{E as B}from"./index-2668a8ea.js";import{v as F}from"./directive-c6f70b8e.js";import{d as V,r as d,M as L,w as M,L as j,b as P,e as U,f as r,x as n,u as t,q as s,p as a,au as $,av as z}from"./runtime-core.esm-bundler-67034826.js";import{_ as A}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-72686045.js";import"./common-46715e7e.js";import"./index-81f2aa1e.js";import"./el-main-7a89c415.js";import"./index-ebd2990f.js";import"./el-overlay-3eff2fc5.js";import"./index-defed8ff.js";import"./focus-trap-83769a43.js";import"./index-6cae7119.js";import"./index-d87ae4a2.js";/* empty css                  *//* empty css                  */import"./index-e09a20f5.js";import"./index-ef31373f.js";import"./index-de22cd40.js";const G="/admin/assets/preview-52aad803.png",H=i=>($("data-v-7a2a3fc4"),i=i(),z(),i),J={class:"main-container"},K={class:"flex ml-[18px] justify-between items-center mt-[20px]"},O={class:"text-[20px]"},Q=H(()=>r("img",{class:"w-[500px]",src:G,alt:""},null,-1)),T=V({__name:"config",setup(i){const f=D().meta.title,m=d(!0),o=L({is_open:!1,request_url:""}),v=d();N(),q().then(p=>{o.request_url=p.data.web_url+"/",m.value=!1});const{copy:g,isSupported:w,copied:c}=I(),x=p=>{if(!w.value){u({message:e("notSupportCopy"),type:"warning"});return}g(p)};M(c,()=>{c.value&&u({message:e("copySuccess"),type:"success"})});const y=()=>{window.open(o.request_url)};return(p,l)=>{const _=S,b=R,h=B,E=k,C=F;return j((P(),U("div",J,[r("div",K,[r("span",O,n(t(f)),1)]),s(E,{model:o,"label-width":"150px",ref_key:"formRef",ref:v,class:"page-form"},{default:a(()=>[s(h,{class:"box-card !border-none",shadow:"never"},{default:a(()=>[s(_,{label:t(e)("preview"),prop:"weapp_name"},{default:a(()=>[Q]),_:1},8,["label"]),s(_,{label:t(e)("PCDomainName")},{default:a(()=>[s(b,{"model-value":o.request_url,class:"input-width",readonly:!0},{append:a(()=>[r("div",{class:"cursor-pointer",onClick:l[0]||(l[0]=X=>x(o.request_url))},n(t(e)("copy")),1)]),_:1},8,["model-value"]),r("span",{class:"ml-2 cursor-pointer visit-btn",onClick:y},n(t(e)("clickVisit")),1)]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])])),[[C,m.value]])}}});const Ie=A(T,[["__scopeId","data-v-7a2a3fc4"]]);export{Ie as default};
