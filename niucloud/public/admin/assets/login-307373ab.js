import"./base-962c0c23.js";/* empty css                   *//* empty css                  *//* empty css                     *//* empty css                */import{s as C}from"./index-5cfb4637.js";/* empty css                    */import{t as i}from"./index-2af60c2e.js";import{J as E,K as y}from"./member-eae88758.js";import{E as B}from"./index-df51d91a.js";import{a as D,E as N}from"./index-61c777fa.js";import{E as S}from"./index-69523418.js";import{E as U}from"./index-bba9e58c.js";import{v as k}from"./directive-c0c3e9a3.js";import{d as F,r as c,M,b as g,e as T,L,m as O,p as d,q as l,f as r,x as n,u as s,v as R}from"./runtime-core.esm-bundler-dc7a07d7.js";import{_ as J}from"./_plugin-vue_export-helper-c27b6911.js";import"./vue-router-79053937.js";import"./el-overlay-60700377.js";import"./event-ff03ec12.js";import"./index-5d86eb33.js";import"./focus-trap-b8b5a003.js";/* empty css                 */import"./el-radio-bfd4b1ad.js";import"./storage-abe718b1.js";import"./index-8bcaafa6.js";import"./index-93f2c618.js";import"./index-7a123a20.js";import"./el-tooltip-58212670.js";import"./el-avatar-3bb47ce2.js";import"./index-d57cc47d.js";import"./common-6291c908.js";import"./common-2cf17469.js";import"./isEqual-c7d5e6d9.js";import"./_Uint8Array-6ff3cafa.js";import"./flatten-d5d1dc97.js";import"./_initCloneObject-28e6bdaa.js";const j={class:"main-container"},A={class:"panel-title"},I={class:"form-tip"},q={class:"form-tip"},K={class:"form-tip"},$={class:"form-tip"},z={class:"panel-title"},G={class:"form-tip"},H={class:"fixed-footer-wrap"},P={class:"fixed-footer"},Q=F({__name:"login",setup(W){const _=c(!0),b=c(),t=M({is_username:0,is_mobile:0,is_auth_register:0,is_bind_mobile:0,agreement_show:0});(async(p=0)=>{const e=await(await E()).data;Object.keys(t).forEach(a=>{e[a]!=null&&(t[a]=Boolean(Number(e[a])))}),_.value=!1})();const u=(p,e)=>{t[e]=p},v=async p=>{_.value||!p||await p.validate(e=>{if(e){const a=JSON.parse(JSON.stringify(t));Object.keys(a).forEach(m=>{a[m]=Number(a[m])}),y(a).then(()=>{_.value=!1}).catch(()=>{_.value=!1})}})};return(p,e)=>{const a=B,m=D,f=C,h=S,V=N,w=U,x=k;return g(),T("div",j,[L((g(),O(V,{model:t,"label-width":"150px",ref_key:"ruleFormRef",ref:b,class:"page-form"},{default:d(()=>[l(h,{class:"box-card !border-none",shadow:"never"},{default:d(()=>[r("h3",A,n(s(i)("commonSetting")),1),l(m,{label:s(i)("logonMode")},{default:d(()=>[l(a,{modelValue:t.is_username,"onUpdate:modelValue":e[0]||(e[0]=o=>t.is_username=o),label:s(i)("isUsername"),onChange:e[1]||(e[1]=o=>u(o,"is_username"))},null,8,["modelValue","label"]),r("div",I,n(s(i)("isUsernameTip")),1),l(a,{modelValue:t.is_mobile,"onUpdate:modelValue":e[2]||(e[2]=o=>t.is_mobile=o),label:s(i)("isMobile"),onChange:e[3]||(e[3]=o=>u(o,"is_mobile"))},null,8,["modelValue","label"]),r("div",q,n(s(i)("isMobileTip")),1)]),_:1},8,["label"]),l(m,{label:s(i)("isBindMobile"),prop:"formData.is_bind_mobile"},{default:d(()=>[l(f,{modelValue:t.is_bind_mobile,"onUpdate:modelValue":e[4]||(e[4]=o=>t.is_bind_mobile=o),onChange:e[5]||(e[5]=o=>u(o,"is_bind_mobile"))},null,8,["modelValue"]),r("div",K,n(s(i)("isBindMobileTip")),1)]),_:1},8,["label"]),l(m,{label:s(i)("agreement"),prop:"formData.agreement_show"},{default:d(()=>[l(f,{modelValue:t.agreement_show,"onUpdate:modelValue":e[6]||(e[6]=o=>t.agreement_show=o),onChange:e[7]||(e[7]=o=>u(o,"agreement_show"))},null,8,["modelValue"]),r("div",$,n(s(i)("agreementTips")),1)]),_:1},8,["label"]),r("h3",z,n(s(i)("tripartiteSetting")),1),l(m,{label:s(i)("isAuthRegister"),prop:"formData.is_auth_register"},{default:d(()=>[l(f,{modelValue:t.is_auth_register,"onUpdate:modelValue":e[8]||(e[8]=o=>t.is_auth_register=o),onChange:e[9]||(e[9]=o=>u(o,"is_auth_register"))},null,8,["modelValue"]),r("div",G,n(s(i)("isAuthRegisterTip")),1)]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[x,_.value]]),r("div",H,[r("div",P,[l(w,{type:"primary",onClick:e[10]||(e[10]=o=>v(b.value))},{default:d(()=>[R(n(s(i)("save")),1)]),_:1})])])])}}});const Te=J(Q,[["__scopeId","data-v-d02a4e89"]]);export{Te as default};