import{i as m,aA as oe,s as se}from"./index-9ef6974e.js";import{n as re,_ as ce}from"./plugin-vue_export-helper-c018272e.js";import{o as k,J as x,r as F,c as ue,d as ae,E as ie,w as O,u as h,A as Z,j as le,g as de}from"./runtime-core.esm-bundler-c17ab5bc.js";import{E as ee}from"./index-4b62c73a.js";const fe=(e,n)=>{if(!m)return!1;const t={undefined:"overflow",true:"overflow-y",false:"overflow-x"}[String(n)],s=oe(e,t);return["scroll","auto","overlay"].some(r=>s.includes(r))},Re=(e,n)=>{if(!m)return;let t=e;for(;t;){if([window,document,document.documentElement].includes(t))return window;if(fe(t,n))return t;t=t.parentNode}return t};let y;const Ne=e=>{var n;if(!m)return 0;if(y!==void 0)return y;const t=document.createElement("div");t.className=`${e}-scrollbar__wrap`,t.style.visibility="hidden",t.style.width="100px",t.style.position="absolute",t.style.top="-9999px",document.body.appendChild(t);const s=t.offsetWidth;t.style.overflow="scroll";const r=document.createElement("div");r.style.width="100%",t.appendChild(r);const u=r.offsetWidth;return(n=t.parentNode)==null||n.removeChild(t),y=s-u,y};function ke(e,n){if(!m)return;if(!n){e.scrollTop=0;return}const t=[];let s=n.offsetParent;for(;s!==null&&e!==s&&e.contains(s);)t.push(s),s=s.offsetParent;const r=n.offsetTop+t.reduce((T,P)=>T+P.offsetTop,0),u=r+n.offsetHeight,d=e.scrollTop,p=d+e.clientHeight;r<d?e.scrollTop=r:u>p&&(e.scrollTop=u-e.clientHeight)}let E=[];const j=e=>{const n=e;n.key===ee.esc&&E.forEach(t=>t(n))},ve=e=>{k(()=>{E.length===0&&document.addEventListener("keydown",j),m&&E.push(e)}),x(()=>{E=E.filter(n=>n!==e),E.length===0&&m&&document.removeEventListener("keydown",j)})},M=F(0),xe=()=>{const e=re("zIndex",2e3),n=ue(()=>e.value+M.value);return{initialZIndex:e,currentZIndex:n,nextZIndex:()=>(M.value++,n.value)}},R="focus-trap.focus-after-trapped",N="focus-trap.focus-after-released",pe="focus-trap.focusout-prevented",q={cancelable:!0,bubbles:!1},Ee={cancelable:!0,bubbles:!1},z="focusAfterTrapped",J="focusAfterReleased",me=Symbol("elFocusTrap"),A=F(),g=F(0),U=F(0);let b=0;const te=e=>{const n=[],t=document.createTreeWalker(e,NodeFilter.SHOW_ELEMENT,{acceptNode:s=>{const r=s.tagName==="INPUT"&&s.type==="hidden";return s.disabled||s.hidden||r?NodeFilter.FILTER_SKIP:s.tabIndex>=0||s===document.activeElement?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_SKIP}});for(;t.nextNode();)n.push(t.currentNode);return n},G=(e,n)=>{for(const t of e)if(!Te(t,n))return t},Te=(e,n)=>{if(getComputedStyle(e).visibility==="hidden")return!0;for(;e;){if(n&&e===n)return!1;if(getComputedStyle(e).display==="none")return!0;e=e.parentElement}return!1},Fe=e=>{const n=te(e),t=G(n,e),s=G(n.reverse(),e);return[t,s]},we=e=>e instanceof HTMLInputElement&&"select"in e,v=(e,n)=>{if(e&&e.focus){const t=document.activeElement;e.focus({preventScroll:!0}),U.value=window.performance.now(),e!==t&&we(e)&&n&&e.select()}};function Y(e,n){const t=[...e],s=e.indexOf(n);return s!==-1&&t.splice(s,1),t}const he=()=>{let e=[];return{push:s=>{const r=e[0];r&&s!==r&&r.pause(),e=Y(e,s),e.unshift(s)},remove:s=>{var r,u;e=Y(e,s),(u=(r=e[0])==null?void 0:r.resume)==null||u.call(r)}}},ye=(e,n=!1)=>{const t=document.activeElement;for(const s of e)if(v(s,n),document.activeElement!==t)return},Q=he(),be=()=>g.value>U.value,_=()=>{A.value="pointer",g.value=window.performance.now()},X=()=>{A.value="keyboard",g.value=window.performance.now()},_e=()=>(k(()=>{b===0&&(document.addEventListener("mousedown",_),document.addEventListener("touchstart",_),document.addEventListener("keydown",X)),b++}),x(()=>{b--,b<=0&&(document.removeEventListener("mousedown",_),document.removeEventListener("touchstart",_),document.removeEventListener("keydown",X))}),{focusReason:A,lastUserFocusTimestamp:g,lastAutomatedFocusTimestamp:U}),S=e=>new CustomEvent(pe,{...Ee,detail:e}),Se=ae({name:"ElFocusTrap",inheritAttrs:!1,props:{loop:Boolean,trapped:Boolean,focusTrapEl:Object,focusStartEl:{type:[Object,String],default:"first"}},emits:[z,J,"focusin","focusout","focusout-prevented","release-requested"],setup(e,{emit:n}){const t=F();let s,r;const{focusReason:u}=_e();ve(o=>{e.trapped&&!d.paused&&n("release-requested",o)});const d={paused:!1,pause(){this.paused=!0},resume(){this.paused=!1}},p=o=>{if(!e.loop&&!e.trapped||d.paused)return;const{key:c,altKey:a,ctrlKey:i,metaKey:l,currentTarget:V,shiftKey:W}=o,{loop:$}=e,ne=c===ee.tab&&!a&&!i&&!l,w=document.activeElement;if(ne&&w){const C=V,[L,I]=Fe(C);if(L&&I){if(!W&&w===I){const f=S({focusReason:u.value});n("focusout-prevented",f),f.defaultPrevented||(o.preventDefault(),$&&v(L,!0))}else if(W&&[L,C].includes(w)){const f=S({focusReason:u.value});n("focusout-prevented",f),f.defaultPrevented||(o.preventDefault(),$&&v(I,!0))}}else if(w===C){const f=S({focusReason:u.value});n("focusout-prevented",f),f.defaultPrevented||o.preventDefault()}}};ie(me,{focusTrapRef:t,onKeydown:p}),O(()=>e.focusTrapEl,o=>{o&&(t.value=o)},{immediate:!0}),O([t],([o],[c])=>{o&&(o.addEventListener("keydown",p),o.addEventListener("focusin",K),o.addEventListener("focusout",D)),c&&(c.removeEventListener("keydown",p),c.removeEventListener("focusin",K),c.removeEventListener("focusout",D))});const T=o=>{n(z,o)},P=o=>n(J,o),K=o=>{const c=h(t);if(!c)return;const a=o.target,i=o.relatedTarget,l=a&&c.contains(a);e.trapped||i&&c.contains(i)||(s=i),l&&n("focusin",o),!d.paused&&e.trapped&&(l?r=a:v(r,!0))},D=o=>{const c=h(t);if(!(d.paused||!c))if(e.trapped){const a=o.relatedTarget;!se(a)&&!c.contains(a)&&setTimeout(()=>{if(!d.paused&&e.trapped){const i=S({focusReason:u.value});n("focusout-prevented",i),i.defaultPrevented||v(r,!0)}},0)}else{const a=o.target;a&&c.contains(a)||n("focusout",o)}};async function H(){await Z();const o=h(t);if(o){Q.push(d);const c=o.contains(document.activeElement)?s:document.activeElement;if(s=c,!o.contains(c)){const i=new Event(R,q);o.addEventListener(R,T),o.dispatchEvent(i),i.defaultPrevented||Z(()=>{let l=e.focusStartEl;le(l)||(v(l),document.activeElement!==l&&(l="first")),l==="first"&&ye(te(o),!0),(document.activeElement===c||l==="container")&&v(o)})}}}function B(){const o=h(t);if(o){o.removeEventListener(R,T);const c=new CustomEvent(N,{...q,detail:{focusReason:u.value}});o.addEventListener(N,P),o.dispatchEvent(c),!c.defaultPrevented&&(u.value=="keyboard"||!be())&&v(s??document.body),o.removeEventListener(N,T),Q.remove(d)}}return k(()=>{e.trapped&&H(),O(()=>e.trapped,o=>{o?H():B()})}),x(()=>{e.trapped&&B()}),{onKeydown:p}}});function ge(e,n,t,s,r,u){return de(e.$slots,"default",{handleKeydown:e.onKeydown})}var Ae=ce(Se,[["render",ge],["__file","/home/runner/work/element-plus/element-plus/packages/components/focus-trap/src/focus-trap.vue"]]);export{Ae as E,me as F,Ne as a,Re as g,ke as s,xe as u};
