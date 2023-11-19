import{C as Q,I as P,U as h,w as _,d as D,f as R}from"./event-a537c4cb.js";import{E as le}from"./index-8cefa3ab.js";import{b as se,o as oe,a as d,F as y,p as ie,k as E,q as ce,u as de,s as me,ao as pe,E as W,G as fe,ap as be}from"./index-72686045.js";import{u as ve,_ as Ne,w as Ve}from"./base-0e92f4db.js";import{u as he}from"./index-6cae7119.js";import{d as X,r as ye,M as Ie,c as V,w as we,o as ge,W as _e,b as f,e as M,L as Y,u as t,n as z,q as K,p as j,m as S,C as H,j as Ee}from"./runtime-core.esm-bundler-67034826.js";import{v as J}from"./index-b340b027.js";const Se=se({id:{type:String,default:void 0},step:{type:Number,default:1},stepStrictly:Boolean,max:{type:Number,default:Number.POSITIVE_INFINITY},min:{type:Number,default:Number.NEGATIVE_INFINITY},modelValue:Number,readonly:Boolean,disabled:Boolean,size:oe,controls:{type:Boolean,default:!0},controlsPosition:{type:String,default:"",values:["","right"]},valueOnClear:{type:[String,Number,null],validator:u=>u===null||d(u)||["min","max"].includes(u),default:null},name:String,label:String,placeholder:String,precision:{type:Number,validator:u=>u>=0&&u===Number.parseInt(`${u}`,10)},validateEvent:{type:Boolean,default:!0}}),Pe={[Q]:(u,k)=>k!==u,blur:u=>u instanceof FocusEvent,focus:u=>u instanceof FocusEvent,[P]:u=>d(u)||y(u),[h]:u=>d(u)||y(u)},ke=["aria-label","onKeydown"],Ae=["aria-label","onKeydown"],Ce=X({name:"ElInputNumber"}),Fe=X({...Ce,props:Se,emits:Pe,setup(u,{expose:k,emit:c}){const a=u,{t:O}=he(),m=ve("input-number"),v=ye(),s=Ie({currentValue:a.modelValue,userInput:null}),{formItem:b}=ie(),U=V(()=>d(a.modelValue)&&a.modelValue<=a.min),G=V(()=>d(a.modelValue)&&a.modelValue>=a.max),Z=V(()=>{const e=$(a.step);return E(a.precision)?Math.max($(a.modelValue),e):(e>a.precision,a.precision)}),A=V(()=>a.controls&&a.controlsPosition==="right"),L=ce(),N=de(),C=V(()=>{if(s.userInput!==null)return s.userInput;let e=s.currentValue;if(y(e))return"";if(d(e)){if(Number.isNaN(e))return"";E(a.precision)||(e=e.toFixed(a.precision))}return e}),F=(e,n)=>{if(E(n)&&(n=Z.value),n===0)return Math.round(e);let r=String(e);const o=r.indexOf(".");if(o===-1||!r.replace(".","").split("")[o+n])return e;const w=r.length;return r.charAt(w-1)==="5"&&(r=`${r.slice(0,Math.max(0,w-1))}6`),Number.parseFloat(Number(r).toFixed(n))},$=e=>{if(y(e))return 0;const n=e.toString(),r=n.indexOf(".");let o=0;return r!==-1&&(o=n.length-r-1),o},q=(e,n=1)=>d(e)?F(e+a.step*n):s.currentValue,x=()=>{if(a.readonly||N.value||G.value)return;const e=Number(C.value)||0,n=q(e);I(n),c(P,s.currentValue)},B=()=>{if(a.readonly||N.value||U.value)return;const e=Number(C.value)||0,n=q(e,-1);I(n),c(P,s.currentValue)},T=(e,n)=>{const{max:r,min:o,step:l,precision:p,stepStrictly:w,valueOnClear:g}=a;let i=Number(e);if(y(e)||Number.isNaN(i))return null;if(e===""){if(g===null)return null;i=Ee(g)?{min:o,max:r}[g]:g}return w&&(i=F(Math.round(i/l)*l,p)),E(p)||(i=F(i,p)),(i>r||i<o)&&(i=i>r?r:o,n&&c(h,i)),i},I=(e,n=!0)=>{var r;const o=s.currentValue,l=T(e);if(o!==l){if(!n){c(h,l);return}s.userInput=null,c(h,l),c(Q,l,o),a.validateEvent&&((r=b==null?void 0:b.validate)==null||r.call(b,"change").catch(p=>R())),s.currentValue=l}},ee=e=>{s.userInput=e;const n=e===""?null:Number(e);c(P,n),I(n,!1)},ne=e=>{const n=e!==""?Number(e):"";(d(n)&&!Number.isNaN(n)||e==="")&&I(n),s.userInput=null},te=()=>{var e,n;(n=(e=v.value)==null?void 0:e.focus)==null||n.call(e)},re=()=>{var e,n;(n=(e=v.value)==null?void 0:e.blur)==null||n.call(e)},ae=e=>{c("focus",e)},ue=e=>{var n;c("blur",e),a.validateEvent&&((n=b==null?void 0:b.validate)==null||n.call(b,"blur").catch(r=>R()))};return we(()=>a.modelValue,e=>{const n=T(s.userInput),r=T(e,!0);!d(n)&&(!n||n!==r)&&(s.currentValue=r,s.userInput=null)},{immediate:!0}),ge(()=>{var e;const{min:n,max:r,modelValue:o}=a,l=(e=v.value)==null?void 0:e.input;if(l.setAttribute("role","spinbutton"),Number.isFinite(r)?l.setAttribute("aria-valuemax",String(r)):l.removeAttribute("aria-valuemax"),Number.isFinite(n)?l.setAttribute("aria-valuemin",String(n)):l.removeAttribute("aria-valuemin"),l.setAttribute("aria-valuenow",String(s.currentValue)),l.setAttribute("aria-disabled",String(N.value)),!d(o)&&o!=null){let p=Number(o);Number.isNaN(p)&&(p=null),c(h,p)}}),_e(()=>{var e;const n=(e=v.value)==null?void 0:e.input;n==null||n.setAttribute("aria-valuenow",`${s.currentValue}`)}),k({focus:te,blur:re}),(e,n)=>(f(),M("div",{class:z([t(m).b(),t(m).m(t(L)),t(m).is("disabled",t(N)),t(m).is("without-controls",!e.controls),t(m).is("controls-right",t(A))]),onDragstart:n[0]||(n[0]=D(()=>{},["prevent"]))},[e.controls?Y((f(),M("span",{key:0,role:"button","aria-label":t(O)("el.inputNumber.decrease"),class:z([t(m).e("decrease"),t(m).is("disabled",t(U))]),onKeydown:_(B,["enter"])},[K(t(W),null,{default:j(()=>[t(A)?(f(),S(t(me),{key:0})):(f(),S(t(pe),{key:1}))]),_:1})],42,ke)),[[t(J),B]]):H("v-if",!0),e.controls?Y((f(),M("span",{key:1,role:"button","aria-label":t(O)("el.inputNumber.increase"),class:z([t(m).e("increase"),t(m).is("disabled",t(G))]),onKeydown:_(x,["enter"])},[K(t(W),null,{default:j(()=>[t(A)?(f(),S(t(fe),{key:0})):(f(),S(t(be),{key:1}))]),_:1})],42,Ae)),[[t(J),x]]):H("v-if",!0),K(t(le),{id:e.id,ref_key:"input",ref:v,type:"number",step:e.step,"model-value":t(C),placeholder:e.placeholder,readonly:e.readonly,disabled:t(N),size:t(L),max:e.max,min:e.min,name:e.name,label:e.label,"validate-event":!1,onKeydown:[_(D(x,["prevent"]),["up"]),_(D(B,["prevent"]),["down"])],onBlur:ue,onFocus:ae,onInput:ee,onChange:ne},null,8,["id","step","model-value","placeholder","readonly","disabled","size","max","min","name","label","onKeydown"])],34))}});var xe=Ne(Fe,[["__file","/home/runner/work/element-plus/element-plus/packages/components/input-number/src/input-number.vue"]]);const Ue=Ve(xe);export{Ue as E};