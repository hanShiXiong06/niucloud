/* empty css             *//* empty css                   *//* empty css                  *//* empty css                     *//* empty css                *//* empty css                 */import{_ as k}from"./index-82132406.js";import{t as l}from"./index-5f4ce139.js";import{C as E,D as U}from"./sys-aa893c6b.js";import{a as D,E as P}from"./index-624573cc.js";import{E as C}from"./index-95693143.js";import{E as N}from"./index-acd12562.js";import{E as R}from"./index-4862d1b3.js";import{v as B}from"./directive-a07a10ed.js";import{d as F,r as g,M as h,b as v,e as L,L as S,m as q,p as i,q as t,f as c,x as u,u as a,v as I}from"./runtime-core.esm-bundler-7c3fd514.js";/* empty css                 */import"./index.vue_vue_type_style_index_0_lang-a42d8a18.js";/* empty css                  */import"./el-overlay-f7f710bd.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-f02197a7.js";import"./index-868cd458.js";import"./index-a3cf5375.js";import"./event-9519ab40.js";import"./focus-trap-bb1e8c7a.js";import"./index-7b0897f9.js";import"./error-492b6a5b.js";import"./attachment-51c3470b.js";/* empty css               *//* empty css                  *//* empty css                  */import"./index-aae906bf.js";import"./vue-router-b5675730.js";import"./el-switch-3d36d31d.js";import"./index-cf47f151.js";import"./index-2083be2e.js";import"./index-47617222.js";import"./validator-62f68fe3.js";import"./el-radio-c9a1047c.js";import"./common-465e36b3.js";import"./index-2f0b1bf3.js";import"./_plugin-vue_export-helper-c27b6911.js";/* empty css                   */import"./el-avatar-7d17482e.js";import"./index-be5dc120.js";/* empty css                      *//* empty css                    *//* empty css                    *//* empty css                   */import"./index-be5868d6.js";import"./index-548a7823.js";import"./index-c656f08b.js";import"./index-9bac81c5.js";import"./index-f97852b4.js";import"./index-381e0c1f.js";import"./index-470ade69.js";import"./isEqual-f40f939e.js";import"./_Uint8Array-de4f83bb.js";import"./flatten-b3585bb8.js";import"./index-800b62de.js";import"./index-9fbce820.js";import"./index-2804b007.js";import"./index-4683bff4.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";import"./_baseClone-cf40e5b2.js";import"./_initCloneObject-bc5ed9bb.js";const O={class:"main-container"},j={class:"panel-title"},M={class:"panel-title"},T={class:"fixed-footer-wrap"},$={class:"fixed-footer"},ao=F({__name:"copyright",setup(z){const m=g(!0),o=h({icp:"",gov_record:"",gov_url:"",market_supervision_url:"",logo:"",company_name:"",copyright_link:"",copyright_desc:""});(async(s=0)=>{const e=await(await E()).data;Object.keys(o).forEach(n=>{e[n]!=null&&(o[n]=e[n])}),m.value=!1})();const _=g(),b=h({site_name:[{required:!0,message:l("siteNamePlaceholder"),trigger:"blur"}]}),y=async s=>{m.value||!s||await s.validate(async e=>{e&&(m.value=!0,U(o).then(()=>{m.value=!1}).catch(()=>{m.value=!1}))})};return(s,e)=>{const n=k,p=D,d=C,f=N,V=P,w=R,x=B;return v(),L("div",O,[S((v(),q(V,{model:o,"label-width":"150px",ref_key:"formRef",ref:_,rules:b,class:"page-form"},{default:i(()=>[t(f,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[c("h3",j,u(a(l)("copyrightEdit")),1),t(p,{label:a(l)("logo")},{default:i(()=>[t(n,{modelValue:o.logo,"onUpdate:modelValue":e[0]||(e[0]=r=>o.logo=r)},null,8,["modelValue"])]),_:1},8,["label"]),t(p,{label:a(l)("companyName"),prop:"company_name"},{default:i(()=>[t(d,{modelValue:o.company_name,"onUpdate:modelValue":e[1]||(e[1]=r=>o.company_name=r),placeholder:a(l)("companyNamePlaceholder"),class:"input-width",clearable:"",maxlength:"30"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:a(l)("copyrightLink")},{default:i(()=>[t(d,{modelValue:o.copyright_link,"onUpdate:modelValue":e[2]||(e[2]=r=>o.copyright_link=r),placeholder:a(l)("copyrightLinkPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:a(l)("copyrightDesc")},{default:i(()=>[t(d,{modelValue:o.copyright_desc,"onUpdate:modelValue":e[3]||(e[3]=r=>o.copyright_desc=r),type:"textarea",rows:"4",clearable:"",placeholder:a(l)("copyrightDescPlaceholder"),class:"input-width",maxlength:"150"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1}),t(f,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:i(()=>[c("h3",M,u(a(l)("putOnRecordEdit")),1),t(p,{label:a(l)("icp"),prop:"icp"},{default:i(()=>[t(d,{modelValue:o.icp,"onUpdate:modelValue":e[4]||(e[4]=r=>o.icp=r),placeholder:a(l)("icpPlaceholder"),class:"input-width",clearable:"",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:a(l)("govRecord")},{default:i(()=>[t(d,{modelValue:o.gov_record,"onUpdate:modelValue":e[5]||(e[5]=r=>o.gov_record=r),placeholder:a(l)("govRecordPlaceholder"),class:"input-width",clearable:"",maxlength:"50"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:a(l)("govUrl")},{default:i(()=>[t(d,{modelValue:o.gov_url,"onUpdate:modelValue":e[6]||(e[6]=r=>o.gov_url=r),placeholder:a(l)("govUrlPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:a(l)("marketSupervisionUrl")},{default:i(()=>[t(d,{modelValue:o.market_supervision_url,"onUpdate:modelValue":e[7]||(e[7]=r=>o.market_supervision_url=r),rows:"4",clearable:"",placeholder:a(l)("marketSupervisionUrlPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1})]),_:1},8,["model","rules"])),[[x,m.value]]),c("div",T,[c("div",$,[t(w,{type:"primary",loading:m.value,onClick:e[8]||(e[8]=r=>y(_.value))},{default:i(()=>[I(u(a(l)("save")),1)]),_:1},8,["loading"])])])])}}});export{ao as default};
