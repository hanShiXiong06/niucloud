/* empty css             *//* empty css                   *//* empty css                  *//* empty css                     *//* empty css                */import{a as E}from"./el-switch-3d36d31d.js";/* empty css                    */import{t as s}from"./index-5f4ce139.js";import{s as w,t as y}from"./member-a9c007ff.js";import{E as B}from"./index-470ade69.js";import{a as D,E as N}from"./index-624573cc.js";import{E as k}from"./index-acd12562.js";import{E as F}from"./index-4862d1b3.js";import{v as M}from"./directive-a07a10ed.js";import{d as U,r as c,M as S,b as v,e as T,L,m as O,p as n,q as r,u as a,f as p,x as _,v as R}from"./runtime-core.esm-bundler-7c3fd514.js";import{_ as j}from"./_plugin-vue_export-helper-c27b6911.js";import"./error-492b6a5b.js";import"./index-868cd458.js";import"./index-a3cf5375.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-f02197a7.js";import"./index-cf47f151.js";import"./focus-trap-bb1e8c7a.js";import"./index-2083be2e.js";import"./index-95693143.js";import"./event-9519ab40.js";import"./index-47617222.js";import"./validator-62f68fe3.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";import"./index-aae906bf.js";import"./vue-router-b5675730.js";import"./el-overlay-f7f710bd.js";import"./index-7b0897f9.js";/* empty css                 */import"./el-radio-c9a1047c.js";import"./common-465e36b3.js";import"./index-2f0b1bf3.js";/* empty css                   */import"./el-avatar-7d17482e.js";import"./index-be5dc120.js";import"./isEqual-f40f939e.js";import"./_Uint8Array-de4f83bb.js";import"./flatten-b3585bb8.js";import"./_baseClone-cf40e5b2.js";import"./_initCloneObject-bc5ed9bb.js";const A={class:"main-container"},I={class:"form-tip"},J={class:"form-tip"},q={class:"form-tip"},$={class:"form-tip"},z={class:"fixed-footer-wrap"},G={class:"fixed-footer"},H=U({__name:"login",setup(K){const d=c(!0),u=c(),t=S({is_username:0,is_mobile:0,is_auth_register:0,is_bind_mobile:0});(async(l=0)=>{const o=await(await w()).data;Object.keys(t).forEach(i=>{o[i]!=null&&(t[i]=Boolean(Number(o[i])))}),d.value=!1})();const f=(l,o)=>{t[o]=l},g=async l=>{d.value||!l||await l.validate(o=>{if(o){let i=JSON.parse(JSON.stringify(t));Object.keys(i).forEach(m=>{i[m]=Number(i[m])}),y(i).then(()=>{d.value=!1}).catch(()=>{d.value=!1})}})};return(l,o)=>{const i=B,m=D,b=E,h=k,V=N,x=F,C=M;return v(),T("div",A,[L((v(),O(V,{model:t,"label-width":"150px",ref_key:"ruleFormRef",ref:u,class:"page-form"},{default:n(()=>[r(h,{class:"box-card !border-none",shadow:"never"},{default:n(()=>[r(m,{label:a(s)("logonMode")},{default:n(()=>[r(i,{modelValue:t.is_username,"onUpdate:modelValue":o[0]||(o[0]=e=>t.is_username=e),label:a(s)("isUsername"),onChange:o[1]||(o[1]=e=>f(e,"is_username"))},null,8,["modelValue","label"]),p("div",I,_(a(s)("isUsernameTip")),1),r(i,{modelValue:t.is_mobile,"onUpdate:modelValue":o[2]||(o[2]=e=>t.is_mobile=e),label:a(s)("isMobile"),onChange:o[3]||(o[3]=e=>f(e,"is_mobile"))},null,8,["modelValue","label"]),p("div",J,_(a(s)("isMobileTip")),1)]),_:1},8,["label"]),r(m,{label:a(s)("isAuthRegister"),prop:"formData.is_auth_register"},{default:n(()=>[r(b,{modelValue:t.is_auth_register,"onUpdate:modelValue":o[4]||(o[4]=e=>t.is_auth_register=e),onChange:o[5]||(o[5]=e=>f(e,"is_auth_register"))},null,8,["modelValue"]),p("div",q,_(a(s)("isAuthRegisterTip")),1)]),_:1},8,["label"]),r(m,{label:a(s)("isBindMobile"),prop:"formData.is_bind_mobile"},{default:n(()=>[r(b,{modelValue:t.is_bind_mobile,"onUpdate:modelValue":o[6]||(o[6]=e=>t.is_bind_mobile=e),onChange:o[7]||(o[7]=e=>f(e,"is_bind_mobile"))},null,8,["modelValue"]),p("div",$,_(a(s)("isBindMobileTip")),1)]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[C,d.value]]),p("div",z,[p("div",G,[r(x,{type:"primary",onClick:o[8]||(o[8]=e=>g(u.value))},{default:n(()=>[R(_(a(s)("save")),1)]),_:1})])])])}}});const qo=j(H,[["__scopeId","data-v-fdbfeab2"]]);export{qo as default};
