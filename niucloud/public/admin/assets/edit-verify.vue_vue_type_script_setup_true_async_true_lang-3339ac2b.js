/* empty css             */import{E as N}from"./el-overlay-380df695.js";/* empty css                  */import{a as R,E as k}from"./el-form-item-144bc604.js";/* empty css                        *//* empty css                 */import{t}from"./index-3862e13d.js";import{c as I}from"./cloneDeep-028669bf.js";import{E as $}from"./index-2f9c8f6b.js";import{E as j}from"./index-6f670765.js";import{d as O,r as p,c as S,b as d,m as f,p as i,f as T,q as o,v as w,x as c,u as m,e as x,F as g,ac as z}from"./runtime-core.esm-bundler-c17ab5bc.js";const A={class:"dialog-footer"},te=O({__name:"edit-verify",emits:["complete"],setup(G,{expose:V,emit:y}){const u=p(!1),E=p(""),_={validate_type:"",min_number:1,max_number:120,betweenMin:1,betweenMax:120},l=p({..._}),v=p(),M=(n,e,a)=>{e?e>l.value.betweenMax?a(new Error(t("minPlaceholder1"))):a():a(new Error(t("minPlaceholder")))},h=(n,e,a)=>{e?e<l.value.betweenMin?a(new Error(t("maxPlaceholder1"))):a():a(new Error(t("maxPlaceholder")))},P=(n,e,a)=>{e>l.value.view_max?a(new Error(t("min1Placeholder1"))):a()},q=(n,e,a)=>{e?e<l.value.view_min?a(new Error(t("max1Placeholder1"))):a():a(new Error(t("max1Placeholder")))},D=S(()=>({min_number:[{required:!0,message:t("minPlaceholder"),trigger:"change"}],max_number:[{required:!0,message:t("maxPlaceholder"),trigger:"change"}],betweenMin:[{required:!0,validator:M,trigger:"change"}],betweenMax:[{required:!0,validator:h,trigger:"change"}],view_min:[{required:!0,validator:P,trigger:"change"}],view_max:[{required:!0,validator:q,trigger:"change"}]})),U=async n=>{n&&await n.validate(async e=>{e&&(y("complete",z(l.value)),u.value=!1)})},F=async(n=null)=>{l.value=I(Object.assign(_,n)),u.value=!0},L=n=>{var e;(e=v.value)==null||e.clearValidate(),n()};return V({showDialog:u,setFormData:F}),(n,e)=>{const a=$,s=R,B=k,b=j,C=N;return d(),f(C,{modelValue:u.value,"onUpdate:modelValue":e[8]||(e[8]=r=>u.value=r),title:E.value,width:"480px","before-close":L,"destroy-on-close":!0},{footer:i(()=>[T("span",A,[o(b,{onClick:e[6]||(e[6]=r=>u.value=!1)},{default:i(()=>[w(c(m(t)("cancel")),1)]),_:1}),o(b,{type:"primary",onClick:e[7]||(e[7]=r=>U(v.value))},{default:i(()=>[w(c(m(t)("confirm")),1)]),_:1})])]),default:i(()=>[o(B,{model:l.value,"label-width":"130px",ref_key:"formRef",ref:v,rules:m(D),class:"page-form"},{default:i(()=>[l.value.validate_type=="min"?(d(),f(s,{key:0,label:m(t)("minLabel"),prop:"min_number"},{default:i(()=>[o(a,{modelValue:l.value.min_number,"onUpdate:modelValue":e[0]||(e[0]=r=>l.value.min_number=r),step:1,"step-strictly":"",min:1,class:"input-width"},null,8,["modelValue"])]),_:1},8,["label"])):l.value.validate_type=="max"?(d(),f(s,{key:1,label:m(t)("maxLabel"),prop:"max_number"},{default:i(()=>[o(a,{modelValue:l.value.max_number,"onUpdate:modelValue":e[1]||(e[1]=r=>l.value.max_number=r),step:1,"step-strictly":"",min:1,class:"input-width"},null,8,["modelValue"])]),_:1},8,["label"])):l.value.view_type==="number"?(d(),x(g,{key:2},[o(s,{label:m(t)("minLabel1"),prop:"view_min"},{default:i(()=>[o(a,{modelValue:l.value.view_min,"onUpdate:modelValue":e[2]||(e[2]=r=>l.value.view_min=r),min:0,"value-on-clear":0,class:"input-width"},null,8,["modelValue"])]),_:1},8,["label"]),o(s,{label:m(t)("maxLabel1"),prop:"view_max"},{default:i(()=>[o(a,{modelValue:l.value.view_max,"onUpdate:modelValue":e[3]||(e[3]=r=>l.value.view_max=r),min:1,class:"input-width"},null,8,["modelValue"])]),_:1},8,["label"])],64)):(d(),x(g,{key:3},[o(s,{label:m(t)("minLabel"),prop:"betweenMin"},{default:i(()=>[o(a,{modelValue:l.value.betweenMin,"onUpdate:modelValue":e[4]||(e[4]=r=>l.value.betweenMin=r),step:1,"step-strictly":"",min:1,class:"input-width"},null,8,["modelValue"])]),_:1},8,["label"]),o(s,{label:m(t)("maxLabel"),prop:"betweenMax"},{default:i(()=>[o(a,{modelValue:l.value.betweenMax,"onUpdate:modelValue":e[5]||(e[5]=r=>l.value.betweenMax=r),step:1,"step-strictly":"",min:1,class:"input-width"},null,8,["modelValue"])]),_:1},8,["label"])],64))]),_:1},8,["model","rules"])]),_:1},8,["modelValue","title"])}}});export{te as _};
