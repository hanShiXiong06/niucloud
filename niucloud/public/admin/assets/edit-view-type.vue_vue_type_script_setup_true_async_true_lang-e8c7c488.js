/* empty css             */import{E as T}from"./el-overlay-380df695.js";/* empty css                  */import{a as N,E as q}from"./el-form-item-144bc604.js";/* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-1bbcede8.js";/* empty css                  */import{t as s}from"./index-3862e13d.js";import{c as A}from"./dict-6b36a05d.js";import{c as L}from"./cloneDeep-028669bf.js";import{a as O,E as P}from"./index-b7b91fcb.js";import{E as S}from"./index-6f670765.js";import{d as U,r as n,c as $,b as p,m as _,p as l,f as j,q as i,v,x as y,u as m,e as I,F as z,t as G,ac as H}from"./runtime-core.esm-bundler-c17ab5bc.js";const J={class:"dialog-footer"},ce=U({__name:"edit-view-type",emits:["complete"],setup(K,{expose:g,emit:b}){const a=n(!1),E=n(""),u={dict_type:""},r=n({...u}),c=n(),d=n([]),V=$(()=>({dict_type:[{required:!0,message:s("dictTypePlaceholder"),trigger:"change"}]})),k=()=>{A().then(t=>{d.value=t.data})},D=async t=>{t&&await t.validate(async e=>{e&&(b("complete",H(r.value)),a.value=!1)})},w=async(t=null)=>{r.value=L(Object.assign(u,t)),k(),a.value=!0},x=t=>{var e;(e=c.value)==null||e.clearValidate(),t()};return g({showDialog:a,setFormData:w}),(t,e)=>{const F=O,h=P,B=N,C=q,f=S,R=T;return p(),_(R,{modelValue:a.value,"onUpdate:modelValue":e[3]||(e[3]=o=>a.value=o),title:E.value,width:"480px","before-close":x,"destroy-on-close":!0},{footer:l(()=>[j("span",J,[i(f,{onClick:e[1]||(e[1]=o=>a.value=!1)},{default:l(()=>[v(y(m(s)("cancel")),1)]),_:1}),i(f,{type:"primary",onClick:e[2]||(e[2]=o=>D(c.value))},{default:l(()=>[v(y(m(s)("confirm")),1)]),_:1})])]),default:l(()=>[i(C,{model:r.value,"label-width":"130px",ref_key:"formRef",ref:c,rules:m(V),class:"page-form"},{default:l(()=>[i(B,{label:m(s)("dictType")},{default:l(()=>[i(h,{class:"input-width",placeholder:m(s)("dictTypePlaceholder"),modelValue:r.value.dict_type,"onUpdate:modelValue":e[0]||(e[0]=o=>r.value.dict_type=o),filterable:"",remote:"",clearable:""},{default:l(()=>[(p(!0),I(z,null,G(d.value,o=>(p(),_(F,{label:o.name,value:o.key,key:o.key},null,8,["label","value"]))),128))]),_:1},8,["placeholder","modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])]),_:1},8,["modelValue","title"])}}});export{ce as _};
