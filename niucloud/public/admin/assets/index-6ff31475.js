import{U as B,C as H,x as C,d as N,f as O}from"./event-3e082a4a.js";import{b as E,A as z,a as $,c as h,v as W,u as J,F as Q,r as X,a7 as Y}from"./index-9ef6974e.js";import{j as w,r as S,D as Z,c as v,d as y,b as k,e as I,f as g,L as P,u as e,i as A,n as f,g as _,v as D,x as F,A as M,h as ee,o as oe,E as ae,M as le,U as se,w as ne}from"./runtime-core.esm-bundler-c17ab5bc.js";import{u as V,_ as G,w as te,a as T}from"./plugin-vue_export-helper-c018272e.js";const U=Symbol("radioGroupKey"),K=E({size:z,disabled:Boolean,label:{type:[String,Number,Boolean],default:""}}),re=E({...K,modelValue:{type:[String,Number,Boolean],default:""},name:{type:String,default:""},border:Boolean}),L={[B]:s=>w(s)||$(s)||h(s),[H]:s=>w(s)||$(s)||h(s)},j=(s,m)=>{const n=S(),a=Z(U,void 0),d=v(()=>!!a),c=v({get(){return d.value?a.modelValue:s.modelValue},set(i){d.value?a.changeEvent(i):m&&m(B,i),n.value.checked=s.modelValue===s.label}}),r=W(v(()=>a==null?void 0:a.size)),u=J(v(()=>a==null?void 0:a.disabled)),l=S(!1),p=v(()=>u.value||d.value&&c.value!==s.label?-1:0);return{radioRef:n,isGroup:d,radioGroup:a,focus:l,size:r,disabled:u,tabIndex:p,modelValue:c}},ie=["value","name","disabled"],de=y({name:"ElRadio"}),ue=y({...de,props:re,emits:L,setup(s,{emit:m}){const n=s,a=V("radio"),{radioRef:d,radioGroup:c,focus:r,size:u,disabled:l,modelValue:p}=j(n,m);function i(){M(()=>m("change",p.value))}return(o,t)=>{var b;return k(),I("label",{class:f([e(a).b(),e(a).is("disabled",e(l)),e(a).is("focus",e(r)),e(a).is("bordered",o.border),e(a).is("checked",e(p)===o.label),e(a).m(e(u))])},[g("span",{class:f([e(a).e("input"),e(a).is("disabled",e(l)),e(a).is("checked",e(p)===o.label)])},[P(g("input",{ref_key:"radioRef",ref:d,"onUpdate:modelValue":t[0]||(t[0]=R=>A(p)?p.value=R:null),class:f(e(a).e("original")),value:o.label,name:o.name||((b=e(c))==null?void 0:b.name),disabled:e(l),type:"radio",onFocus:t[1]||(t[1]=R=>r.value=!0),onBlur:t[2]||(t[2]=R=>r.value=!1),onChange:i},null,42,ie),[[C,e(p)]]),g("span",{class:f(e(a).e("inner"))},null,2)],2),g("span",{class:f(e(a).e("label")),onKeydown:t[3]||(t[3]=N(()=>{},["stop"]))},[_(o.$slots,"default",{},()=>[D(F(o.label),1)])],34)],2)}}});var pe=G(ue,[["__file","/home/runner/work/element-plus/element-plus/packages/components/radio/src/radio.vue"]]);const me=E({...K,name:{type:String,default:""}}),ce=["value","name","disabled"],fe=y({name:"ElRadioButton"}),be=y({...fe,props:me,setup(s){const m=s,n=V("radio"),{radioRef:a,focus:d,size:c,disabled:r,modelValue:u,radioGroup:l}=j(m),p=v(()=>({backgroundColor:(l==null?void 0:l.fill)||"",borderColor:(l==null?void 0:l.fill)||"",boxShadow:l!=null&&l.fill?`-1px 0 0 0 ${l.fill}`:"",color:(l==null?void 0:l.textColor)||""}));return(i,o)=>{var t;return k(),I("label",{class:f([e(n).b("button"),e(n).is("active",e(u)===i.label),e(n).is("disabled",e(r)),e(n).is("focus",e(d)),e(n).bm("button",e(c))])},[P(g("input",{ref_key:"radioRef",ref:a,"onUpdate:modelValue":o[0]||(o[0]=b=>A(u)?u.value=b:null),class:f(e(n).be("button","original-radio")),value:i.label,type:"radio",name:i.name||((t=e(l))==null?void 0:t.name),disabled:e(r),onFocus:o[1]||(o[1]=b=>d.value=!0),onBlur:o[2]||(o[2]=b=>d.value=!1)},null,42,ce),[[C,e(u)]]),g("span",{class:f(e(n).be("button","inner")),style:ee(e(u)===i.label?e(p):{}),onKeydown:o[3]||(o[3]=N(()=>{},["stop"]))},[_(i.$slots,"default",{},()=>[D(F(i.label),1)])],38)],2)}}});var q=G(be,[["__file","/home/runner/work/element-plus/element-plus/packages/components/radio/src/radio-button.vue"]]);const ve=E({id:{type:String,default:void 0},size:z,disabled:Boolean,modelValue:{type:[String,Number,Boolean],default:""},fill:{type:String,default:""},label:{type:String,default:void 0},textColor:{type:String,default:""},name:{type:String,default:void 0},validateEvent:{type:Boolean,default:!0}}),ge=L,ye=["id","aria-label","aria-labelledby"],Ee=y({name:"ElRadioGroup"}),Re=y({...Ee,props:ve,emits:ge,setup(s,{emit:m}){const n=s,a=V("radio"),d=Q(),c=S(),{formItem:r}=X(),{inputId:u,isLabeledByFormItem:l}=Y(n,{formItemContext:r}),p=o=>{m(B,o),M(()=>m("change",o))};oe(()=>{const o=c.value.querySelectorAll("[type=radio]"),t=o[0];!Array.from(o).some(b=>b.checked)&&t&&(t.tabIndex=0)});const i=v(()=>n.name||d.value);return ae(U,le({...se(n),changeEvent:p,name:i})),ne(()=>n.modelValue,()=>{n.validateEvent&&(r==null||r.validate("change").catch(o=>O()))}),(o,t)=>(k(),I("div",{id:e(u),ref_key:"radioGroupRef",ref:c,class:f(e(a).b("group")),role:"radiogroup","aria-label":e(l)?void 0:o.label||"radio-group","aria-labelledby":e(l)?e(r).labelId:void 0},[_(o.$slots,"default")],10,ye))}});var x=G(Re,[["__file","/home/runner/work/element-plus/element-plus/packages/components/radio/src/radio-group.vue"]]);const _e=te(pe,{RadioButton:q,RadioGroup:x}),Ve=T(x),Ge=T(q);export{_e as E,Ge as a,Ve as b};