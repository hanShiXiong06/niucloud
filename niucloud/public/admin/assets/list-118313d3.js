/* empty css             *//* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-aae906bf.js";/* empty css                   *//* empty css                 *//* empty css                    *//* empty css                        *//* empty css                *//* empty css                     *//* empty css                  */import{t as i}from"./index-5f4ce139.js";import{j as U,q as j,r as G}from"./site-ceac4417.js";import{a as R}from"./vue-router-b5675730.js";import{_ as q}from"./edit-site.vue_vue_type_script_setup_true_lang-0aac9878.js";import{E as A}from"./index-4862d1b3.js";import{E as M}from"./index-95693143.js";import{a as O,E as H}from"./index-624573cc.js";import{a as J,E as K}from"./index-9fbce820.js";import{E as Q}from"./index-acd12562.js";import{a as W,E as X}from"./index-a9458a49.js";import{E as Y}from"./index-2804b007.js";import{E as Z}from"./index-800b62de.js";import{v as ee}from"./directive-a07a10ed.js";import{d as te,r as h,M as ae,b as s,e as _,q as o,p as r,f as w,v as m,x as p,u as e,F as L,t as V,m as f,L as oe}from"./runtime-core.esm-bundler-7c3fd514.js";import"./el-overlay-f7f710bd.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-f02197a7.js";import"./index-868cd458.js";import"./index-a3cf5375.js";import"./event-9519ab40.js";import"./focus-trap-bb1e8c7a.js";import"./index-7b0897f9.js";import"./error-492b6a5b.js";import"./el-switch-3d36d31d.js";import"./index-cf47f151.js";import"./index-2083be2e.js";import"./index-47617222.js";import"./validator-62f68fe3.js";import"./el-radio-c9a1047c.js";import"./common-465e36b3.js";import"./index-2f0b1bf3.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-avatar-7d17482e.js";import"./index-be5dc120.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";/* empty css                  *//* empty css                       */import"./index-7175b959.js";import"./flatten-b3585bb8.js";import"./_Uint8Array-de4f83bb.js";import"./index-6ed8f3b9.js";import"./isEqual-f40f939e.js";import"./index-4683bff4.js";import"./index-c656f08b.js";import"./_baseClone-cf40e5b2.js";import"./_initCloneObject-bc5ed9bb.js";import"./index-470ade69.js";import"./_isIterateeCall-7a6fae02.js";const le={class:"main-container"},re={class:"flex justify-between"},ie={class:"mt-[16px]"},se={key:0},pe={key:1},ne={class:"mt-[16px] flex justify-end"},kt=te({__name:"list",setup(me){const E=h([]),k=h([]);let a=ae({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{keywords:"",group_id:"",status:""}});(async()=>{E.value=await(await U({})).data})(),(async()=>{k.value=await(await j()).data})();const x=h(),d=(u=1)=>{a.loading=!0,a.page=u,G({page:a.page,limit:a.limit,...a.searchParam}).then(l=>{a.loading=!1,a.data=l.data.data,a.total=l.data.total}).catch(()=>{a.loading=!1})};d();const D=R(),y=h(null),F=u=>{y.value.setFormData(),y.value.showDialog=!0},T=u=>{D.push("/site/info?id="+u.site_id)};return(u,l)=>{const g=A,z=M,b=O,v=J,P=K,I=H,C=Q,n=W,S=Y,N=X,$=Z,B=ee;return s(),_("div",le,[o(C,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[w("div",re,[o(g,{type:"primary",onClick:F},{default:r(()=>[m(p(e(i)("addSite")),1)]),_:1})]),o(C,{class:"box-card !border-none my-[16px] table-search-wrap",shadow:"never"},{default:r(()=>[o(I,{inline:!0,model:e(a).searchParam,ref_key:"searchFormRef",ref:x},{default:r(()=>[o(b,{label:e(i)("siteName"),prop:"site_name"},{default:r(()=>[o(z,{modelValue:e(a).searchParam.keywords,"onUpdate:modelValue":l[0]||(l[0]=t=>e(a).searchParam.keywords=t),placeholder:e(i)("siteNamePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(b,{label:e(i)("groupId"),prop:"group_id"},{default:r(()=>[o(P,{modelValue:e(a).searchParam.group_id,"onUpdate:modelValue":l[1]||(l[1]=t=>e(a).searchParam.group_id=t),clearable:"",placeholder:e(i)("groupIdPlaceholder"),class:"input-width"},{default:r(()=>[o(v,{label:e(i)("selectPlaceholder"),value:""},null,8,["label"]),(s(!0),_(L,null,V(E.value,t=>(s(),f(v,{label:t.group_name,value:t.group_id},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),o(b,{label:e(i)("status"),prop:"status"},{default:r(()=>[o(P,{modelValue:e(a).searchParam.status,"onUpdate:modelValue":l[2]||(l[2]=t=>e(a).searchParam.status=t),clearable:"",placeholder:e(i)("groupIdPlaceholder"),class:"input-width"},{default:r(()=>[o(v,{label:e(i)("selectPlaceholder"),value:""},null,8,["label"]),(s(!0),_(L,null,V(k.value,(t,c)=>(s(),f(v,{label:t,value:c},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),o(b,null,{default:r(()=>[o(g,{type:"primary",onClick:l[3]||(l[3]=t=>d())},{default:r(()=>[m(p(e(i)("search")),1)]),_:1}),o(g,{onClick:l[4]||(l[4]=t=>{var c;return(c=x.value)==null?void 0:c.resetFields()})},{default:r(()=>[m(p(e(i)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),w("div",ie,[oe((s(),f(N,{data:e(a).data,size:"large"},{empty:r(()=>[w("span",null,p(e(a).loading?"":e(i)("emptyData")),1)]),default:r(()=>[o(n,{prop:"site_name",label:e(i)("siteName"),"min-width":"120","show-overflow-tooltip":!0},null,8,["label"]),o(n,{prop:"group_name",label:e(i)("groupId"),"min-width":"120","show-overflow-tooltip":!0},null,8,["label"]),o(n,{label:e(i)("status"),"min-width":"120",align:"center"},{default:r(({row:t})=>[t.status==1?(s(),f(S,{key:0,class:"ml-2",type:"success"},{default:r(()=>[m(p(t.status_name),1)]),_:2},1024)):(s(),f(S,{key:1,class:"ml-2",type:"error"},{default:r(()=>[m(p(t.status_name),1)]),_:2},1024))]),_:1},8,["label"]),o(n,{prop:"create_time",label:e(i)("createTime"),"min-width":"140","show-overflow-tooltip":!0},null,8,["label"]),o(n,{prop:"expire_time",label:e(i)("expireTime"),"min-width":"140","show-overflow-tooltip":!0},{default:r(({row:t})=>[t.expire_time==0?(s(),_("div",se,"永久")):(s(),_("div",pe,p(t.expire_time),1))]),_:1},8,["label"]),o(n,{label:e(i)("operation"),fixed:"right",width:"100"},{default:r(({row:t})=>[o(g,{type:"primary",link:"",onClick:c=>T(t)},{default:r(()=>[m(p(e(i)("info")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[B,e(a).loading]]),w("div",ne,[o($,{"current-page":e(a).page,"onUpdate:currentPage":l[5]||(l[5]=t=>e(a).page=t),"page-size":e(a).limit,"onUpdate:pageSize":l[6]||(l[6]=t=>e(a).limit=t),layout:"total, sizes, prev, pager, next, jumper",total:e(a).total,onSizeChange:l[7]||(l[7]=t=>d()),onCurrentChange:d},null,8,["current-page","page-size","total"])])])]),_:1}),o(q,{ref_key:"addSiteDialog",ref:y,onComplete:l[8]||(l[8]=t=>d())},null,512)])}}});export{kt as default};
