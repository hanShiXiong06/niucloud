import{d as ge,v as Be,a as Re}from"./error-492b6a5b.js";import{d as De}from"./index-2083be2e.js";import{i as xe,a as Oe,b as Ke,V as He,E as $}from"./index-868cd458.js";import{L as je,M as Ue,e as We}from"./index-a3cf5375.js";import{a as we,i as Xe}from"./index-f02197a7.js";import{c,K as Ye,r as V,j as J,d as Ee,l as qe,G as Ge,s as Q,w as ee,A as L,o as Ze,$ as Je,L as Qe,b as p,e as g,C as v,F as te,n as h,u as t,g as H,f as P,m as w,p as A,U as j,y as ae,q as et,N as tt,x as U,h as at,k as Se}from"./runtime-core.esm-bundler-7c3fd514.js";import{L as ot,b as nt,g as st,d as oe,h as lt,c as rt,u as Ce,r as it,_ as ut,w as ct}from"./plugin-vue_export-helper-edbdb6f8.js";import{U as ne}from"./event-9519ab40.js";const dt=o=>/([(\uAC00-\uD7AF)|(\u3130-\u318F)])+/gi.test(o),pt=o=>o,ft=["class","style"],vt=/^on[A-Z]/,mt=(o={})=>{const{excludeListeners:m=!1,excludeKeys:s}=o,a=c(()=>((s==null?void 0:s.value)||[]).concat(ft)),l=Ye();return l?c(()=>{var r;return ot(Object.entries((r=l.proxy)==null?void 0:r.$attrs).filter(([u])=>!a.value.includes(u)&&!(m&&vt.test(u))))}):c(()=>({}))};function ht(o){const m=V();function s(){if(o.value==null)return;const{selectionStart:l,selectionEnd:r,value:u}=o.value;if(l==null||r==null)return;const x=u.slice(0,Math.max(0,l)),d=u.slice(Math.max(0,r));m.value={selectionStart:l,selectionEnd:r,value:u,beforeTxt:x,afterTxt:d}}function a(){if(o.value==null||m.value==null)return;const{value:l}=o.value,{beforeTxt:r,afterTxt:u,selectionStart:x}=m.value;if(r==null||u==null||x==null)return;let d=l.length;if(l.endsWith(u))d=l.length-u.length;else if(l.startsWith(r))d=r.length;else{const y=r[x-1],S=l.indexOf(y,x-1);S!==-1&&(d=S+1)}o.value.setSelectionRange(d,d)}return[s,a]}let b;const yt=`
  height:0 !important;
  visibility:hidden !important;
  overflow:hidden !important;
  position:absolute !important;
  z-index:-1000 !important;
  top:0 !important;
  right:0 !important;
`,bt=["letter-spacing","line-height","padding-top","padding-bottom","font-family","font-weight","font-size","text-rendering","text-transform","width","text-indent","padding-left","padding-right","border-width","box-sizing"];function gt(o){const m=window.getComputedStyle(o),s=m.getPropertyValue("box-sizing"),a=Number.parseFloat(m.getPropertyValue("padding-bottom"))+Number.parseFloat(m.getPropertyValue("padding-top")),l=Number.parseFloat(m.getPropertyValue("border-bottom-width"))+Number.parseFloat(m.getPropertyValue("border-top-width"));return{contextStyle:bt.map(u=>`${u}:${m.getPropertyValue(u)}`).join(";"),paddingSize:a,borderSize:l,boxSizing:s}}function Ie(o,m=1,s){var a;b||(b=document.createElement("textarea"),document.body.appendChild(b));const{paddingSize:l,borderSize:r,boxSizing:u,contextStyle:x}=gt(o);b.setAttribute("style",`${x};${yt}`),b.value=o.value||o.placeholder||"";let d=b.scrollHeight;const y={};u==="border-box"?d=d+r:u==="content-box"&&(d=d-l),b.value="";const S=b.scrollHeight-l;if(we(m)){let f=S*m;u==="border-box"&&(f=f+l+r),d=Math.max(f,d),y.minHeight=`${f}px`}if(we(s)){let f=S*s;u==="border-box"&&(f=f+l+r),d=Math.min(f,d)}return y.height=`${d}px`,(a=b.parentNode)==null||a.removeChild(b),b=void 0,y}const xt=nt({id:{type:String,default:void 0},size:st,disabled:Boolean,modelValue:{type:oe([String,Number,Object]),default:""},type:{type:String,default:"text"},resize:{type:String,values:["none","both","horizontal","vertical"]},autosize:{type:oe([Boolean,Object]),default:!1},autocomplete:{type:String,default:"off"},formatter:{type:Function},parser:{type:Function},placeholder:{type:String},form:{type:String},readonly:{type:Boolean,default:!1},clearable:{type:Boolean,default:!1},showPassword:{type:Boolean,default:!1},showWordLimit:{type:Boolean,default:!1},suffixIcon:{type:xe},prefixIcon:{type:xe},containerRole:{type:String,default:void 0},label:{type:String,default:void 0},tabindex:{type:[String,Number],default:0},validateEvent:{type:Boolean,default:!0},inputStyle:{type:oe([Object,Array,String]),default:()=>pt({})}}),wt={[ne]:o=>J(o),input:o=>J(o),change:o=>J(o),focus:o=>o instanceof FocusEvent,blur:o=>o instanceof FocusEvent,clear:()=>!0,mouseleave:o=>o instanceof MouseEvent,mouseenter:o=>o instanceof MouseEvent,keydown:o=>o instanceof Event,compositionstart:o=>o instanceof CompositionEvent,compositionupdate:o=>o instanceof CompositionEvent,compositionend:o=>o instanceof CompositionEvent},St=["role"],Ct=["id","type","disabled","formatter","parser","readonly","autocomplete","tabindex","aria-label","placeholder","form"],It=["id","tabindex","disabled","readonly","autocomplete","aria-label","placeholder","form"],Et=Ee({name:"ElInput",inheritAttrs:!1}),kt=Ee({...Et,props:xt,emits:wt,setup(o,{expose:m,emit:s}){const a=o,l=qe(),r=Ge(),u=c(()=>{const e={};return a.containerRole==="combobox"&&(e["aria-haspopup"]=l["aria-haspopup"],e["aria-owns"]=l["aria-owns"],e["aria-expanded"]=l["aria-expanded"]),e}),x=c(()=>[a.type==="textarea"?le.b():n.b(),n.m(ke.value),n.is("disabled",E.value),n.is("exceed",Ve.value),{[n.b("group")]:r.prepend||r.append,[n.bm("group","append")]:r.append,[n.bm("group","prepend")]:r.prepend,[n.m("prefix")]:r.prefix||a.prefixIcon,[n.m("suffix")]:r.suffix||a.suffixIcon||a.clearable||a.showPassword,[n.bm("suffix","password-clear")]:D.value&&Y.value},l.class]),d=c(()=>[n.e("wrapper"),n.is("focus",N.value)]),y=mt({excludeKeys:c(()=>Object.keys(u.value))}),{form:S,formItem:f}=Oe(),{inputId:se}=Ke(a,{formItemContext:f}),ke=lt(),E=rt(),n=Ce("input"),le=Ce("textarea"),B=Q(),k=Q(),N=V(!1),W=V(!1),F=V(!1),R=V(!1),re=V(),X=Q(a.inputStyle),T=c(()=>B.value||k.value),ie=c(()=>{var e;return(e=S==null?void 0:S.statusIcon)!=null?e:!1}),M=c(()=>(f==null?void 0:f.validateState)||""),ue=c(()=>M.value&&He[M.value]),ze=c(()=>R.value?je:Ue),Pe=c(()=>[l.style,a.inputStyle]),ce=c(()=>[a.inputStyle,X.value,{resize:a.resize}]),C=c(()=>it(a.modelValue)?"":String(a.modelValue)),D=c(()=>a.clearable&&!E.value&&!a.readonly&&!!C.value&&(N.value||W.value)),Y=c(()=>a.showPassword&&!E.value&&!a.readonly&&!!C.value&&(!!C.value||N.value)),z=c(()=>a.showWordLimit&&!!y.value.maxlength&&(a.type==="text"||a.type==="textarea")&&!E.value&&!a.readonly&&!a.showPassword),q=c(()=>Array.from(C.value).length),Ve=c(()=>!!z.value&&q.value>Number(y.value.maxlength)),Ne=c(()=>!!r.suffix||!!a.suffixIcon||D.value||a.showPassword||z.value||!!M.value&&ie.value),[Fe,Te]=ht(B);De(k,e=>{if(!z.value||a.resize!=="both")return;const i=e[0],{width:I}=i.contentRect;re.value={right:`calc(100% - ${I+15+6}px)`}});const O=()=>{const{type:e,autosize:i}=a;if(!(!Xe||e!=="textarea"))if(i){const I=Se(i)?i.minRows:void 0,Z=Se(i)?i.maxRows:void 0;X.value={...Ie(k.value,I,Z)}}else X.value={minHeight:Ie(k.value).minHeight}},_=()=>{const e=T.value;!e||e.value===C.value||(e.value=C.value)},G=async e=>{Fe();let{value:i}=e.target;if(a.formatter&&(i=a.parser?a.parser(i):i,i=a.formatter(i)),!F.value){if(i===C.value){_();return}s(ne,i),s("input",i),await L(),_(),Te()}},de=e=>{s("change",e.target.value)},pe=e=>{s("compositionstart",e),F.value=!0},fe=e=>{var i;s("compositionupdate",e);const I=(i=e.target)==null?void 0:i.value,Z=I[I.length-1]||"";F.value=!dt(Z)},ve=e=>{s("compositionend",e),F.value&&(F.value=!1,G(e))},Me=()=>{R.value=!R.value,K()},K=async()=>{var e;await L(),(e=T.value)==null||e.focus()},_e=()=>{var e;return(e=T.value)==null?void 0:e.blur()},me=e=>{N.value=!0,s("focus",e)},he=e=>{var i;N.value=!1,s("blur",e),a.validateEvent&&((i=f==null?void 0:f.validate)==null||i.call(f,"blur").catch(I=>ge()))},$e=e=>{W.value=!1,s("mouseleave",e)},Le=e=>{W.value=!0,s("mouseenter",e)},ye=e=>{s("keydown",e)},Ae=()=>{var e;(e=T.value)==null||e.select()},be=()=>{s(ne,""),s("change",""),s("clear"),s("input","")};return ee(()=>a.modelValue,()=>{var e;L(()=>O()),a.validateEvent&&((e=f==null?void 0:f.validate)==null||e.call(f,"change").catch(i=>ge()))}),ee(C,()=>_()),ee(()=>a.type,async()=>{await L(),_(),O()}),Ze(()=>{!a.formatter&&a.parser,_(),L(O)}),m({input:B,textarea:k,ref:T,textareaStyle:ce,autosize:Je(a,"autosize"),focus:K,blur:_e,select:Ae,clear:be,resizeTextarea:O}),(e,i)=>Qe((p(),g("div",ae(t(u),{class:t(x),style:t(Pe),role:e.containerRole,onMouseenter:Le,onMouseleave:$e}),[v(" input "),e.type!=="textarea"?(p(),g(te,{key:0},[v(" prepend slot "),e.$slots.prepend?(p(),g("div",{key:0,class:h(t(n).be("group","prepend"))},[H(e.$slots,"prepend")],2)):v("v-if",!0),P("div",{class:h(t(d))},[v(" prefix slot "),e.$slots.prefix||e.prefixIcon?(p(),g("span",{key:0,class:h(t(n).e("prefix"))},[P("span",{class:h(t(n).e("prefix-inner")),onClick:K},[H(e.$slots,"prefix"),e.prefixIcon?(p(),w(t($),{key:0,class:h(t(n).e("icon"))},{default:A(()=>[(p(),w(j(e.prefixIcon)))]),_:1},8,["class"])):v("v-if",!0)],2)],2)):v("v-if",!0),P("input",ae({id:t(se),ref_key:"input",ref:B,class:t(n).e("inner")},t(y),{type:e.showPassword?R.value?"text":"password":e.type,disabled:t(E),formatter:e.formatter,parser:e.parser,readonly:e.readonly,autocomplete:e.autocomplete,tabindex:e.tabindex,"aria-label":e.label,placeholder:e.placeholder,style:e.inputStyle,form:a.form,onCompositionstart:pe,onCompositionupdate:fe,onCompositionend:ve,onInput:G,onFocus:me,onBlur:he,onChange:de,onKeydown:ye}),null,16,Ct),v(" suffix slot "),t(Ne)?(p(),g("span",{key:1,class:h(t(n).e("suffix"))},[P("span",{class:h(t(n).e("suffix-inner")),onClick:K},[!t(D)||!t(Y)||!t(z)?(p(),g(te,{key:0},[H(e.$slots,"suffix"),e.suffixIcon?(p(),w(t($),{key:0,class:h(t(n).e("icon"))},{default:A(()=>[(p(),w(j(e.suffixIcon)))]),_:1},8,["class"])):v("v-if",!0)],64)):v("v-if",!0),t(D)?(p(),w(t($),{key:1,class:h([t(n).e("icon"),t(n).e("clear")]),onMousedown:Re(t(tt),["prevent"]),onClick:be},{default:A(()=>[et(t(We))]),_:1},8,["class","onMousedown"])):v("v-if",!0),t(Y)?(p(),w(t($),{key:2,class:h([t(n).e("icon"),t(n).e("password")]),onClick:Me},{default:A(()=>[(p(),w(j(t(ze))))]),_:1},8,["class"])):v("v-if",!0),t(z)?(p(),g("span",{key:3,class:h(t(n).e("count"))},[P("span",{class:h(t(n).e("count-inner"))},U(t(q))+" / "+U(t(y).maxlength),3)],2)):v("v-if",!0),t(M)&&t(ue)&&t(ie)?(p(),w(t($),{key:4,class:h([t(n).e("icon"),t(n).e("validateIcon"),t(n).is("loading",t(M)==="validating")])},{default:A(()=>[(p(),w(j(t(ue))))]),_:1},8,["class"])):v("v-if",!0)],2)],2)):v("v-if",!0)],2),v(" append slot "),e.$slots.append?(p(),g("div",{key:1,class:h(t(n).be("group","append"))},[H(e.$slots,"append")],2)):v("v-if",!0)],64)):(p(),g(te,{key:1},[v(" textarea "),P("textarea",ae({id:t(se),ref_key:"textarea",ref:k,class:t(le).e("inner")},t(y),{tabindex:e.tabindex,disabled:t(E),readonly:e.readonly,autocomplete:e.autocomplete,style:t(ce),"aria-label":e.label,placeholder:e.placeholder,form:a.form,onCompositionstart:pe,onCompositionupdate:fe,onCompositionend:ve,onInput:G,onFocus:me,onBlur:he,onChange:de,onKeydown:ye}),null,16,It),t(z)?(p(),g("span",{key:0,style:at(re.value),class:h(t(n).e("count"))},U(t(q))+" / "+U(t(y).maxlength),7)):v("v-if",!0)],64))],16,St)),[[Be,e.type!=="hidden"]])}});var zt=ut(kt,[["__file","/home/runner/work/element-plus/element-plus/packages/components/input/src/input.vue"]]);const Lt=ct(zt);export{Lt as E,dt as i,pt as m,mt as u};
