/* empty css             *//* empty css                     *//* empty css                *//* empty css                  *//* empty css               *//* empty css                 */import{u as V,t as a}from"./index-5f4ce139.js";import{i as j}from"./common-465e36b3.js";import{k as q}from"./site-ceac4417.js";import{u as O,a as X}from"./vue-router-b5675730.js";import{_ as A}from"./edit-site.vue_vue_type_script_setup_true_lang-0aac9878.js";import{x as L}from"./index-aae906bf.js";import{a as M,E as z}from"./index-624573cc.js";import{E as G}from"./index-f97852b4.js";import{E as H}from"./index-2804b007.js";import{E as J}from"./index-4862d1b3.js";import{E as K}from"./index-acd12562.js";import{d as P,r as y,w as Q,M as U,b as d,e as W,q as r,p as e,f as m,x as i,u as o,m as u,C as _,v as p}from"./runtime-core.esm-bundler-7c3fd514.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";import"./index-a3cf5375.js";import"./index-2083be2e.js";import"./index-f02197a7.js";/* empty css                   *//* empty css                  */import"./el-overlay-f7f710bd.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-868cd458.js";import"./event-9519ab40.js";import"./focus-trap-bb1e8c7a.js";import"./index-7b0897f9.js";import"./error-492b6a5b.js";/* empty css                       *//* empty css                 *//* empty css                   *//* empty css                  *//* empty css                  */import"./index-95693143.js";import"./index-9fbce820.js";import"./index-cf47f151.js";import"./index-2f0b1bf3.js";import"./isEqual-f40f939e.js";import"./_Uint8Array-de4f83bb.js";import"./index-47617222.js";import"./validator-62f68fe3.js";import"./index-7175b959.js";import"./flatten-b3585bb8.js";import"./index-6ed8f3b9.js";import"./index-4683bff4.js";import"./index-c656f08b.js";import"./directive-a07a10ed.js";import"./el-switch-3d36d31d.js";import"./el-radio-c9a1047c.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-avatar-7d17482e.js";import"./index-be5dc120.js";import"./_baseClone-cf40e5b2.js";import"./_initCloneObject-bc5ed9bb.js";const Y={class:"main-container"},Z={class:"panel-title"},tt={class:"input-width"},et={class:"input-width"},ot={class:"input-width"},at={class:"input-width"},rt={class:"input-width"},st={class:"fixed-footer-wrap"},it={class:"fixed-footer"},ce=P({__name:"info",setup(lt){const k=V(),D=L(),f=O(),N=X(),b=parseInt(f.query.id),E=y(!0);k.pageReturn=!0,Q(f,(n,s)=>{k.pageReturn=!1});const x={site_id:0,site_name:"",expire_time:0,group_id:0,keywords:"",business_hours:"",logo:"",desc:"",latitude:"",longitude:"",province_id:"",city_id:"",district_id:"",address:"",full_address:"",phone:"",group_name:"",status:0,create_time:0},t=U({...x}),S=async(n=0)=>{Object.assign(t,x);const s=await(await q(n)).data;Object.keys(t).forEach(l=>{s[l]!=null&&(t[l]=s[l])}),E.value=!1};b?S(b):E.value=!1;const R=()=>{S(b)},v=y(),g=y(null),T=n=>{g.value.setFormData(t),g.value.showDialog=!0},B=async n=>{C()},C=()=>{D.removeTab(f.path),N.push({path:"/site/list"})};return(n,s)=>{const l=M,F=G,h=H,w=J,I=K,$=z;return d(),W("div",Y,[r($,{model:t,"label-width":"150px",ref_key:"formRef",ref:v,class:"page-form"},{default:e(()=>[r(I,{class:"box-card !border-none relative",shadow:"never"},{default:e(()=>[m("h3",Z,i(o(a)("siteInfo")),1),r(l,{label:o(a)("siteName")},{default:e(()=>[m("div",tt,i(t.site_name),1)]),_:1},8,["label"]),r(l,{label:o(a)("siteLogo")},{default:e(()=>[t.logo?(d(),u(F,{key:0,class:"w-20 h-20",src:o(j)(t.logo),fit:"contain"},null,8,["src"])):_("",!0)]),_:1},8,["label"]),r(l,{label:o(a)("groupName")},{default:e(()=>[m("div",et,i(t.group_name||""),1)]),_:1},8,["label"]),r(l,{label:o(a)("keywords")},{default:e(()=>[m("div",ot,i(t.keywords||""),1)]),_:1},8,["label"]),r(l,{label:o(a)("status")},{default:e(({row:c})=>[t.status==1?(d(),u(h,{key:0,class:"ml-2",type:"success"},{default:e(()=>[p(i(o(a)("statusNormal")),1)]),_:1})):_("",!0),t.status==0?(d(),u(h,{key:1,class:"ml-2",type:"error"},{default:e(()=>[p(i(o(a)("statusDeactivate")),1)]),_:1})):_("",!0),t.status==2?(d(),u(h,{key:2,class:"ml-2",type:"error"},{default:e(()=>[p(i(o(a)("statusExpire")),1)]),_:1})):_("",!0)]),_:1},8,["label"]),r(l,{label:o(a)("createTime")},{default:e(()=>[m("div",at,i(t.create_time||""),1)]),_:1},8,["label"]),r(l,{label:o(a)("expireTime")},{default:e(()=>[m("div",rt,i(t.expire_time||""),1)]),_:1},8,["label"]),r(w,{class:"absolute right-5 top-5",type:"primary",onClick:s[0]||(s[0]=c=>T(v.value))},{default:e(()=>[p(i(o(a)("edit")),1)]),_:1})]),_:1})]),_:1},8,["model"]),r(A,{ref_key:"editSiteDialog",ref:g,onComplete:s[1]||(s[1]=c=>R())},null,512),m("div",st,[m("div",it,[r(w,{type:"primary",onClick:s[2]||(s[2]=c=>B(v.value))},{default:e(()=>[p(i(o(a)("confirm")),1)]),_:1}),r(w,{onClick:s[3]||(s[3]=c=>C())},{default:e(()=>[p(i(o(a)("cancel")),1)]),_:1})])])])}}});export{ce as default};
