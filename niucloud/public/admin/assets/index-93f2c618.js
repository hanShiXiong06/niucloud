import{U as ne,e as Le,f as ge,v as Re,d as De}from"./event-ff03ec12.js";import{aJ as Ke,a as xe,b as Oe,t as He,d as Z,A as we,v as je,I as Ue,x as We,f as Xe,u as Se,V as Ye,aK as qe,aL as Ge,N as Je,E as A,y as Ze,_ as Qe,i as et,w as tt}from"./base-962c0c23.js";import{c,K as at,r as N,j as Q,d as Ee,l as ot,G as nt,s as ee,w as te,A as M,o as st,$ as lt,L as rt,b as p,e as g,C as v,F as ae,n as y,u as t,g as H,f as P,m as w,p as B,U as j,y as oe,q as it,N as ut,x as U,h as ct,k as Ce}from"./runtime-core.esm-bundler-dc7a07d7.js";const dt=o=>/([(\uAC00-\uD7AF)|(\u3130-\u318F)])+/gi.test(o),pt=o=>o,ft=["class","style"],vt=/^on[A-Z]/,mt=(o={})=>{const{excludeListeners:m=!1,excludeKeys:s}=o,a=c(()=>((s==null?void 0:s.value)||[]).concat(ft)),l=at();return l?c(()=>{var r;return Ke(Object.entries((r=l.proxy)==null?void 0:r.$attrs).filter(([u])=>!a.value.includes(u)&&!(m&&vt.test(u))))}):c(()=>({}))};function yt(o){const m=N();function s(){if(o.value==null)return;const{selectionStart:l,selectionEnd:r,value:u}=o.value;if(l==null||r==null)return;const x=u.slice(0,Math.max(0,l)),d=u.slice(Math.max(0,r));m.value={selectionStart:l,selectionEnd:r,value:u,beforeTxt:x,afterTxt:d}}function a(){if(o.value==null||m.value==null)return;const{value:l}=o.value,{beforeTxt:r,afterTxt:u,selectionStart:x}=m.value;if(r==null||u==null||x==null)return;let d=l.length;if(l.endsWith(u))d=l.length-u.length;else if(l.startsWith(r))d=r.length;else{const h=r[x-1],S=l.indexOf(h,x-1);S!==-1&&(d=S+1)}o.value.setSelectionRange(d,d)}return[s,a]}let b;const ht=`
  height:0 !important;
  visibility:hidden !important;
  overflow:hidden !important;
  position:absolute !important;
  z-index:-1000 !important;
  top:0 !important;
  right:0 !important;
`,bt=["letter-spacing","line-height","padding-top","padding-bottom","font-family","font-weight","font-size","text-rendering","text-transform","width","text-indent","padding-left","padding-right","border-width","box-sizing"];function gt(o){const m=window.getComputedStyle(o),s=m.getPropertyValue("box-sizing"),a=Number.parseFloat(m.getPropertyValue("padding-bottom"))+Number.parseFloat(m.getPropertyValue("padding-top")),l=Number.parseFloat(m.getPropertyValue("border-bottom-width"))+Number.parseFloat(m.getPropertyValue("border-top-width"));return{contextStyle:bt.map(u=>`${u}:${m.getPropertyValue(u)}`).join(";"),paddingSize:a,borderSize:l,boxSizing:s}}function Ie(o,m=1,s){var a;b||(b=document.createElement("textarea"),document.body.appendChild(b));const{paddingSize:l,borderSize:r,boxSizing:u,contextStyle:x}=gt(o);b.setAttribute("style",`${x};${ht}`),b.value=o.value||o.placeholder||"";let d=b.scrollHeight;const h={};u==="border-box"?d=d+r:u==="content-box"&&(d=d-l),b.value="";const S=b.scrollHeight-l;if(xe(m)){let f=S*m;u==="border-box"&&(f=f+l+r),d=Math.max(f,d),h.minHeight=`${f}px`}if(xe(s)){let f=S*s;u==="border-box"&&(f=f+l+r),d=Math.min(f,d)}return h.height=`${d}px`,(a=b.parentNode)==null||a.removeChild(b),b=void 0,h}const xt=Oe({id:{type:String,default:void 0},size:He,disabled:Boolean,modelValue:{type:Z([String,Number,Object]),default:""},type:{type:String,default:"text"},resize:{type:String,values:["none","both","horizontal","vertical"]},autosize:{type:Z([Boolean,Object]),default:!1},autocomplete:{type:String,default:"off"},formatter:{type:Function},parser:{type:Function},placeholder:{type:String},form:{type:String},readonly:{type:Boolean,default:!1},clearable:{type:Boolean,default:!1},showPassword:{type:Boolean,default:!1},showWordLimit:{type:Boolean,default:!1},suffixIcon:{type:we},prefixIcon:{type:we},containerRole:{type:String,default:void 0},label:{type:String,default:void 0},tabindex:{type:[String,Number],default:0},validateEvent:{type:Boolean,default:!0},inputStyle:{type:Z([Object,Array,String]),default:()=>pt({})}}),wt={[ne]:o=>Q(o),input:o=>Q(o),change:o=>Q(o),focus:o=>o instanceof FocusEvent,blur:o=>o instanceof FocusEvent,clear:()=>!0,mouseleave:o=>o instanceof MouseEvent,mouseenter:o=>o instanceof MouseEvent,keydown:o=>o instanceof Event,compositionstart:o=>o instanceof CompositionEvent,compositionupdate:o=>o instanceof CompositionEvent,compositionend:o=>o instanceof CompositionEvent},St=["role"],Ct=["id","type","disabled","formatter","parser","readonly","autocomplete","tabindex","aria-label","placeholder","form"],It=["id","tabindex","disabled","readonly","autocomplete","aria-label","placeholder","form"],Et=Ee({name:"ElInput",inheritAttrs:!1}),kt=Ee({...Et,props:xt,emits:wt,setup(o,{expose:m,emit:s}){const a=o,l=ot(),r=nt(),u=c(()=>{const e={};return a.containerRole==="combobox"&&(e["aria-haspopup"]=l["aria-haspopup"],e["aria-owns"]=l["aria-owns"],e["aria-expanded"]=l["aria-expanded"]),e}),x=c(()=>[a.type==="textarea"?le.b():n.b(),n.m(ke.value),n.is("disabled",E.value),n.is("exceed",Ne.value),{[n.b("group")]:r.prepend||r.append,[n.bm("group","append")]:r.append,[n.bm("group","prepend")]:r.prepend,[n.m("prefix")]:r.prefix||a.prefixIcon,[n.m("suffix")]:r.suffix||a.suffixIcon||a.clearable||a.showPassword,[n.bm("suffix","password-clear")]:D.value&&Y.value},l.class]),d=c(()=>[n.e("wrapper"),n.is("focus",V.value)]),h=mt({excludeKeys:c(()=>Object.keys(u.value))}),{form:S,formItem:f}=je(),{inputId:se}=Ue(a,{formItemContext:f}),ke=We(),E=Xe(),n=Se("input"),le=Se("textarea"),L=ee(),k=ee(),V=N(!1),W=N(!1),F=N(!1),R=N(!1),re=N(),X=ee(a.inputStyle),T=c(()=>L.value||k.value),ie=c(()=>{var e;return(e=S==null?void 0:S.statusIcon)!=null?e:!1}),_=c(()=>(f==null?void 0:f.validateState)||""),ue=c(()=>_.value&&Ye[_.value]),ze=c(()=>R.value?qe:Ge),Pe=c(()=>[l.style,a.inputStyle]),ce=c(()=>[a.inputStyle,X.value,{resize:a.resize}]),C=c(()=>Je(a.modelValue)?"":String(a.modelValue)),D=c(()=>a.clearable&&!E.value&&!a.readonly&&!!C.value&&(V.value||W.value)),Y=c(()=>a.showPassword&&!E.value&&!a.readonly&&!!C.value&&(!!C.value||V.value)),z=c(()=>a.showWordLimit&&!!h.value.maxlength&&(a.type==="text"||a.type==="textarea")&&!E.value&&!a.readonly&&!a.showPassword),q=c(()=>Array.from(C.value).length),Ne=c(()=>!!z.value&&q.value>Number(h.value.maxlength)),Ve=c(()=>!!r.suffix||!!a.suffixIcon||D.value||a.showPassword||z.value||!!_.value&&ie.value),[Fe,Te]=yt(L);Le(k,e=>{if(!z.value||a.resize!=="both")return;const i=e[0],{width:I}=i.contentRect;re.value={right:`calc(100% - ${I+15+6}px)`}});const K=()=>{const{type:e,autosize:i}=a;if(!(!et||e!=="textarea"))if(i){const I=Ce(i)?i.minRows:void 0,J=Ce(i)?i.maxRows:void 0;X.value={...Ie(k.value,I,J)}}else X.value={minHeight:Ie(k.value).minHeight}},$=()=>{const e=T.value;!e||e.value===C.value||(e.value=C.value)},G=async e=>{Fe();let{value:i}=e.target;if(a.formatter&&(i=a.parser?a.parser(i):i,i=a.formatter(i)),!F.value){if(i===C.value){$();return}s(ne,i),s("input",i),await M(),$(),Te()}},de=e=>{s("change",e.target.value)},pe=e=>{s("compositionstart",e),F.value=!0},fe=e=>{var i;s("compositionupdate",e);const I=(i=e.target)==null?void 0:i.value,J=I[I.length-1]||"";F.value=!dt(J)},ve=e=>{s("compositionend",e),F.value&&(F.value=!1,G(e))},_e=()=>{R.value=!R.value,O()},O=async()=>{var e;await M(),(e=T.value)==null||e.focus()},$e=()=>{var e;return(e=T.value)==null?void 0:e.blur()},me=e=>{V.value=!0,s("focus",e)},ye=e=>{var i;V.value=!1,s("blur",e),a.validateEvent&&((i=f==null?void 0:f.validate)==null||i.call(f,"blur").catch(I=>ge()))},Ae=e=>{W.value=!1,s("mouseleave",e)},Me=e=>{W.value=!0,s("mouseenter",e)},he=e=>{s("keydown",e)},Be=()=>{var e;(e=T.value)==null||e.select()},be=()=>{s(ne,""),s("change",""),s("clear"),s("input","")};return te(()=>a.modelValue,()=>{var e;M(()=>K()),a.validateEvent&&((e=f==null?void 0:f.validate)==null||e.call(f,"change").catch(i=>ge()))}),te(C,()=>$()),te(()=>a.type,async()=>{await M(),$(),K()}),st(()=>{!a.formatter&&a.parser,$(),M(K)}),m({input:L,textarea:k,ref:T,textareaStyle:ce,autosize:lt(a,"autosize"),focus:O,blur:$e,select:Be,clear:be,resizeTextarea:K}),(e,i)=>rt((p(),g("div",oe(t(u),{class:t(x),style:t(Pe),role:e.containerRole,onMouseenter:Me,onMouseleave:Ae}),[v(" input "),e.type!=="textarea"?(p(),g(ae,{key:0},[v(" prepend slot "),e.$slots.prepend?(p(),g("div",{key:0,class:y(t(n).be("group","prepend"))},[H(e.$slots,"prepend")],2)):v("v-if",!0),P("div",{class:y(t(d))},[v(" prefix slot "),e.$slots.prefix||e.prefixIcon?(p(),g("span",{key:0,class:y(t(n).e("prefix"))},[P("span",{class:y(t(n).e("prefix-inner")),onClick:O},[H(e.$slots,"prefix"),e.prefixIcon?(p(),w(t(A),{key:0,class:y(t(n).e("icon"))},{default:B(()=>[(p(),w(j(e.prefixIcon)))]),_:1},8,["class"])):v("v-if",!0)],2)],2)):v("v-if",!0),P("input",oe({id:t(se),ref_key:"input",ref:L,class:t(n).e("inner")},t(h),{type:e.showPassword?R.value?"text":"password":e.type,disabled:t(E),formatter:e.formatter,parser:e.parser,readonly:e.readonly,autocomplete:e.autocomplete,tabindex:e.tabindex,"aria-label":e.label,placeholder:e.placeholder,style:e.inputStyle,form:a.form,onCompositionstart:pe,onCompositionupdate:fe,onCompositionend:ve,onInput:G,onFocus:me,onBlur:ye,onChange:de,onKeydown:he}),null,16,Ct),v(" suffix slot "),t(Ve)?(p(),g("span",{key:1,class:y(t(n).e("suffix"))},[P("span",{class:y(t(n).e("suffix-inner")),onClick:O},[!t(D)||!t(Y)||!t(z)?(p(),g(ae,{key:0},[H(e.$slots,"suffix"),e.suffixIcon?(p(),w(t(A),{key:0,class:y(t(n).e("icon"))},{default:B(()=>[(p(),w(j(e.suffixIcon)))]),_:1},8,["class"])):v("v-if",!0)],64)):v("v-if",!0),t(D)?(p(),w(t(A),{key:1,class:y([t(n).e("icon"),t(n).e("clear")]),onMousedown:De(t(ut),["prevent"]),onClick:be},{default:B(()=>[it(t(Ze))]),_:1},8,["class","onMousedown"])):v("v-if",!0),t(Y)?(p(),w(t(A),{key:2,class:y([t(n).e("icon"),t(n).e("password")]),onClick:_e},{default:B(()=>[(p(),w(j(t(ze))))]),_:1},8,["class"])):v("v-if",!0),t(z)?(p(),g("span",{key:3,class:y(t(n).e("count"))},[P("span",{class:y(t(n).e("count-inner"))},U(t(q))+" / "+U(t(h).maxlength),3)],2)):v("v-if",!0),t(_)&&t(ue)&&t(ie)?(p(),w(t(A),{key:4,class:y([t(n).e("icon"),t(n).e("validateIcon"),t(n).is("loading",t(_)==="validating")])},{default:B(()=>[(p(),w(j(t(ue))))]),_:1},8,["class"])):v("v-if",!0)],2)],2)):v("v-if",!0)],2),v(" append slot "),e.$slots.append?(p(),g("div",{key:1,class:y(t(n).be("group","append"))},[H(e.$slots,"append")],2)):v("v-if",!0)],64)):(p(),g(ae,{key:1},[v(" textarea "),P("textarea",oe({id:t(se),ref_key:"textarea",ref:k,class:t(le).e("inner")},t(h),{tabindex:e.tabindex,disabled:t(E),readonly:e.readonly,autocomplete:e.autocomplete,style:t(ce),"aria-label":e.label,placeholder:e.placeholder,form:a.form,onCompositionstart:pe,onCompositionupdate:fe,onCompositionend:ve,onInput:G,onFocus:me,onBlur:ye,onChange:de,onKeydown:he}),null,16,It),t(z)?(p(),g("span",{key:0,style:ct(re.value),class:y(t(n).e("count"))},U(t(q))+" / "+U(t(h).maxlength),7)):v("v-if",!0)],64))],16,St)),[[Re,e.type!=="hidden"]])}});var zt=Qe(kt,[["__file","/home/runner/work/element-plus/element-plus/packages/components/input/src/input.vue"]]);const Ft=tt(zt);export{Ft as E,dt as i,pt as m,mt as u};