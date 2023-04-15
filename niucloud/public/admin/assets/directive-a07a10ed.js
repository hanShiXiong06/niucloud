import{r as h,M as B,Y as I,P as f,p as N,L as S,q as V,A as w,j as A,k as _,i as E,a5 as P}from"./runtime-core.esm-bundler-7c3fd514.js";import{c as O,v as j,T as R}from"./error-492b6a5b.js";import{u as $,y as b,$ as m,x as C}from"./plugin-vue_export-helper-edbdb6f8.js";import{b as q}from"./focus-trap-bb1e8c7a.js";import{i as M}from"./index-f02197a7.js";function Y(t){let e;const l=$("loading"),n=h(!1),o=B({...t,originalPosition:"",originalOverflow:"",visible:!1});function a(s){o.text=s}function c(){const s=o.parent;if(!s.vLoadingAddClassList){let u=s.getAttribute("loading-number");u=Number.parseInt(u)-1,u?s.setAttribute("loading-number",u.toString()):(b(s,l.bm("parent","relative")),s.removeAttribute("loading-number")),b(s,l.bm("parent","hidden"))}d(),v.unmount()}function d(){var s,u;(u=(s=r.$el)==null?void 0:s.parentNode)==null||u.removeChild(r.$el)}function p(){var s;t.beforeClose&&!t.beforeClose()||(n.value=!0,clearTimeout(e),e=window.setTimeout(i,400),o.visible=!1,(s=t.closed)==null||s.call(t))}function i(){if(!n.value)return;const s=o.parent;n.value=!1,s.vLoadingAddClassList=void 0,c()}const v=O({name:"ElLoading",setup(){return()=>{const s=o.spinner||o.svg,u=f("svg",{class:"circular",viewBox:o.svgViewBox?o.svgViewBox:"0 0 50 50",...s?{innerHTML:s}:{}},[f("circle",{class:"path",cx:"25",cy:"25",r:"20",fill:"none"})]),T=o.text?f("p",{class:l.b("text")},[o.text]):void 0;return f(R,{name:l.b("fade"),onAfterLeave:i},{default:N(()=>[S(V("div",{style:{backgroundColor:o.background||""},class:[l.b("mask"),o.customClass,o.fullscreen?"is-fullscreen":""]},[f("div",{class:l.b("spinner")},[u,T])]),[[j,o.visible]])])})}}}),r=v.mount(document.createElement("div"));return{...I(o),setText:a,removeElLoadingChild:d,close:p,handleAfterLeave:i,vm:r,get $el(){return r.$el}}}let g;const Z=function(t={}){if(!M)return;const e=z(t);if(e.fullscreen&&g)return g;const l=Y({...e,closed:()=>{var o;(o=e.closed)==null||o.call(e),e.fullscreen&&(g=void 0)}});D(e,e.parent,l),L(e,e.parent,l),e.parent.vLoadingAddClassList=()=>L(e,e.parent,l);let n=e.parent.getAttribute("loading-number");return n?n=`${Number.parseInt(n)+1}`:n="1",e.parent.setAttribute("loading-number",n),e.parent.appendChild(l.$el),w(()=>l.visible.value=e.visible),e.fullscreen&&(g=l),l},z=t=>{var e,l,n,o;let a;return A(t.target)?a=(e=document.querySelector(t.target))!=null?e:document.body:a=t.target||document.body,{parent:a===document.body||t.body?document.body:a,background:t.background||"",svg:t.svg||"",svgViewBox:t.svgViewBox||"",spinner:t.spinner||!1,text:t.text||"",fullscreen:a===document.body&&((l=t.fullscreen)!=null?l:!0),lock:(n=t.lock)!=null?n:!1,customClass:t.customClass||"",visible:(o=t.visible)!=null?o:!0,target:a}},D=async(t,e,l)=>{const{nextZIndex:n}=q(),o={};if(t.fullscreen)l.originalPosition.value=m(document.body,"position"),l.originalOverflow.value=m(document.body,"overflow"),o.zIndex=n();else if(t.parent===document.body){l.originalPosition.value=m(document.body,"position"),await w();for(const a of["top","left"]){const c=a==="top"?"scrollTop":"scrollLeft";o[a]=`${t.target.getBoundingClientRect()[a]+document.body[c]+document.documentElement[c]-Number.parseInt(m(document.body,`margin-${a}`),10)}px`}for(const a of["height","width"])o[a]=`${t.target.getBoundingClientRect()[a]}px`}else l.originalPosition.value=m(e,"position");for(const[a,c]of Object.entries(o))l.$el.style[a]=c},L=(t,e,l)=>{const n=$("loading");["absolute","fixed","sticky"].includes(l.originalPosition.value)?b(e,n.bm("parent","relative")):C(e,n.bm("parent","relative")),t.fullscreen&&t.lock?C(e,n.bm("parent","hidden")):b(e,n.bm("parent","hidden"))},x=Symbol("ElLoading"),k=(t,e)=>{var l,n,o,a;const c=e.instance,d=r=>_(e.value)?e.value[r]:void 0,p=r=>{const s=A(r)&&(c==null?void 0:c[r])||r;return s&&h(s)},i=r=>p(d(r)||t.getAttribute(`element-loading-${P(r)}`)),y=(l=d("fullscreen"))!=null?l:e.modifiers.fullscreen,v={text:i("text"),svg:i("svg"),svgViewBox:i("svgViewBox"),spinner:i("spinner"),background:i("background"),customClass:i("customClass"),fullscreen:y,target:(n=d("target"))!=null?n:y?void 0:t,body:(o=d("body"))!=null?o:e.modifiers.body,lock:(a=d("lock"))!=null?a:e.modifiers.lock};t[x]={options:v,instance:Z(v)}},F=(t,e)=>{for(const l of Object.keys(e))E(e[l])&&(e[l].value=t[l])},U={mounted(t,e){e.value&&k(t,e)},updated(t,e){const l=t[x];e.oldValue!==e.value&&(e.value&&!e.oldValue?k(t,e):e.value&&e.oldValue?_(e.value)&&F(e.value,l.options):l==null||l.instance.close())},unmounted(t){var e;(e=t[x])==null||e.instance.close()}};export{Z as L,U as v};
