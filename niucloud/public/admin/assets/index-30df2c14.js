import{W as st,m as ne,U as Mt,aa as ot,ab as G,q as It,ac as D,ad as Rt,G as P,ae as de,af as pe,ag as $t,o as xt,S as Lt,ah as Ft,P as se,F as it,ai as Dt,aj as oe,ak as X,al as K,d as jt,E as at,am as te,y as lt,an as le,ao as Vt,a1 as kt,ap as Bt,aq as zt,ar as Pe,as as Te,at as Ht,au as Ut,av as Wt,aw as qt,ax as Qt,ay as ct,az as Kt,w as M,c as H,r as y,u as Jt,a as Gt,s as Xt,X as Yt}from"./base-04829be5.js";import{i as ie,P as ut,Q as re,R as Y,S as Zt,T as ae,U as J,W as ft,X as en,Y as tn,L as nn,Z as rn}from"./index-7e933ae4.js";const sn="http://www.w3.org/2000/svg",B=typeof document<"u"?document:null,Ae=B&&B.createElement("template"),on={insert:(e,t,n)=>{t.insertBefore(e,n||null)},remove:e=>{const t=e.parentNode;t&&t.removeChild(e)},createElement:(e,t,n,r)=>{const s=t?B.createElementNS(sn,e):B.createElement(e,n?{is:n}:void 0);return e==="select"&&r&&r.multiple!=null&&s.setAttribute("multiple",r.multiple),s},createText:e=>B.createTextNode(e),createComment:e=>B.createComment(e),setText:(e,t)=>{e.nodeValue=t},setElementText:(e,t)=>{e.textContent=t},parentNode:e=>e.parentNode,nextSibling:e=>e.nextSibling,querySelector:e=>B.querySelector(e),setScopeId(e,t){e.setAttribute(t,"")},insertStaticContent(e,t,n,r,s,o){const i=n?n.previousSibling:t.lastChild;if(s&&(s===o||s.nextSibling))for(;t.insertBefore(s.cloneNode(!0),n),!(s===o||!(s=s.nextSibling)););else{Ae.innerHTML=r?`<svg>${e}</svg>`:e;const a=Ae.content;if(r){const l=a.firstChild;for(;l.firstChild;)a.appendChild(l.firstChild);a.removeChild(l)}t.insertBefore(a,n)}return[i?i.nextSibling:t.firstChild,n?n.previousSibling:t.lastChild]}};function an(e,t,n){const r=e._vtc;r&&(t=(t?[t,...r]:[...r]).join(" ")),t==null?e.removeAttribute("class"):n?e.setAttribute("class",t):e.className=t}function ln(e,t,n){const r=e.style,s=ne(n);if(n&&!s){if(t&&!ne(t))for(const o in t)n[o]==null&&me(r,o,"");for(const o in n)me(r,o,n[o])}else{const o=r.display;s?t!==n&&(r.cssText=n):t&&e.removeAttribute("style"),"_vod"in e&&(r.display=o)}}const Ne=/\s*!important$/;function me(e,t,n){if(P(n))n.forEach(r=>me(e,t,r));else if(n==null&&(n=""),t.startsWith("--"))e.setProperty(t,n);else{const r=cn(e,t);Ne.test(n)?e.setProperty(D(r),n.replace(Ne,""),"important"):e[r]=n}}const Me=["Webkit","Moz","ms"],ce={};function cn(e,t){const n=ce[t];if(n)return n;let r=te(t);if(r!=="filter"&&r in e)return ce[t]=r;r=qt(r);for(let s=0;s<Me.length;s++){const o=Me[s]+r;if(o in e)return ce[t]=o}return t}const Ie="http://www.w3.org/1999/xlink";function un(e,t,n,r,s){if(r&&t.startsWith("xlink:"))n==null?e.removeAttributeNS(Ie,t.slice(6,t.length)):e.setAttributeNS(Ie,t,n);else{const o=Qt(t);n==null||o&&!ct(n)?e.removeAttribute(t):e.setAttribute(t,o?"":n)}}function fn(e,t,n,r,s,o,i){if(t==="innerHTML"||t==="textContent"){r&&i(r,s,o),e[t]=n??"";return}if(t==="value"&&e.tagName!=="PROGRESS"&&!e.tagName.includes("-")){e._value=n;const l=n??"";(e.value!==l||e.tagName==="OPTION")&&(e.value=l),n==null&&e.removeAttribute(t);return}let a=!1;if(n===""||n==null){const l=typeof e[t];l==="boolean"?n=ct(n):n==null&&l==="string"?(n="",a=!0):l==="number"&&(n=0,a=!0)}try{e[t]=n}catch{}a&&e.removeAttribute(t)}function $(e,t,n,r){e.addEventListener(t,n,r)}function dn(e,t,n,r){e.removeEventListener(t,n,r)}function pn(e,t,n,r,s=null){const o=e._vei||(e._vei={}),i=o[t];if(r&&i)i.value=r;else{const[a,l]=mn(t);if(r){const c=o[t]=gn(r,s);$(e,a,c,l)}else i&&(dn(e,a,i,l),o[t]=void 0)}}const Re=/(?:Once|Passive|Capture)$/;function mn(e){let t;if(Re.test(e)){t={};let r;for(;r=e.match(Re);)e=e.slice(0,e.length-r[0].length),t[r[0].toLowerCase()]=!0}return[e[2]===":"?e.slice(3):D(e.slice(2)),t]}let ue=0;const hn=Promise.resolve(),vn=()=>ue||(hn.then(()=>ue=0),ue=Date.now());function gn(e,t){const n=r=>{if(!r._vts)r._vts=Date.now();else if(r._vts<=n.attached)return;Kt(_n(r,n.value),t,5,[r])};return n.value=e,n.attached=vn(),n}function _n(e,t){if(P(t)){const n=e.stopImmediatePropagation;return e.stopImmediatePropagation=()=>{n.call(e),e._stopped=!0},t.map(r=>s=>!s._stopped&&r&&r(s))}else return t}const $e=/^on[a-z]/,bn=(e,t,n,r,s=!1,o,i,a,l)=>{t==="class"?an(e,r,s):t==="style"?ln(e,n,r):Ht(t)?Ut(t)||pn(e,t,n,r,i):(t[0]==="."?(t=t.slice(1),!0):t[0]==="^"?(t=t.slice(1),!1):yn(e,t,r,s))?fn(e,t,r,o,i,a,l):(t==="true-value"?e._trueValue=r:t==="false-value"&&(e._falseValue=r),un(e,t,r,s))};function yn(e,t,n,r){return r?!!(t==="innerHTML"||t==="textContent"||t in e&&$e.test(t)&&st(n)):t==="spellcheck"||t==="draggable"||t==="translate"||t==="form"||t==="list"&&e.tagName==="INPUT"||t==="type"&&e.tagName==="TEXTAREA"||$e.test(t)&&ne(n)?!1:t in e}function Sn(e,t){const n=jt(e);class r extends ye{constructor(o){super(n,o,t)}}return r.def=n,r}const Cr=e=>Sn(e,kn),wn=typeof HTMLElement<"u"?HTMLElement:class{};class ye extends wn{constructor(t,n={},r){super(),this._def=t,this._props=n,this._instance=null,this._connected=!1,this._resolved=!1,this._numberProps=null,this.shadowRoot&&r?r(this._createVNode(),this.shadowRoot):(this.attachShadow({mode:"open"}),this._def.__asyncLoader||this._resolveProps(this._def))}connectedCallback(){this._connected=!0,this._instance||(this._resolved?this._update():this._resolveDef())}disconnectedCallback(){this._connected=!1,at(()=>{this._connected||(He(null,this.shadowRoot),this._instance=null)})}_resolveDef(){this._resolved=!0;for(let r=0;r<this.attributes.length;r++)this._setAttr(this.attributes[r].name);new MutationObserver(r=>{for(const s of r)this._setAttr(s.attributeName)}).observe(this,{attributes:!0});const t=(r,s=!1)=>{const{props:o,styles:i}=r;let a;if(o&&!P(o))for(const l in o){const c=o[l];(c===Number||c&&c.type===Number)&&(l in this._props&&(this._props[l]=de(this._props[l])),(a||(a=Object.create(null)))[te(l)]=!0)}this._numberProps=a,s&&this._resolveProps(r),this._applyStyles(i),this._update()},n=this._def.__asyncLoader;n?n().then(r=>t(r,!0)):t(this._def)}_resolveProps(t){const{props:n}=t,r=P(n)?n:Object.keys(n||{});for(const s of Object.keys(this))s[0]!=="_"&&r.includes(s)&&this._setProp(s,this[s],!0,!1);for(const s of r.map(te))Object.defineProperty(this,s,{get(){return this._getProp(s)},set(o){this._setProp(s,o)}})}_setAttr(t){let n=this.getAttribute(t);const r=te(t);this._numberProps&&this._numberProps[r]&&(n=de(n)),this._setProp(r,n,!1)}_getProp(t){return this._props[t]}_setProp(t,n,r=!0,s=!0){n!==this._props[t]&&(this._props[t]=n,s&&this._instance&&this._update(),r&&(n===!0?this.setAttribute(D(t),""):typeof n=="string"||typeof n=="number"?this.setAttribute(D(t),n+""):n||this.removeAttribute(D(t))))}_update(){He(this._createVNode(),this.shadowRoot)}_createVNode(){const t=lt(this._def,G({},this._props));return this._instance||(t.ce=n=>{this._instance=n,n.isCE=!0;const r=(o,i)=>{this.dispatchEvent(new CustomEvent(o,{detail:i}))};n.emit=(o,...i)=>{r(o,i),D(o)!==o&&r(D(o),i)};let s=this;for(;s=s&&(s.parentNode||s.host);)if(s instanceof ye){n.parent=s._instance,n.provides=s._instance.provides;break}}),t}_applyStyles(t){t&&t.forEach(n=>{const r=document.createElement("style");r.textContent=n,this.shadowRoot.appendChild(r)})}}function Or(e="$style"){{const t=se();if(!t)return le;const n=t.type.__cssModules;if(!n)return le;const r=n[e];return r||le}}function Pr(e){const t=se();if(!t)return;const n=t.ut=(s=e(t.proxy))=>{Array.from(document.querySelectorAll(`[data-v-owner="${t.uid}"]`)).forEach(o=>ve(o,s))},r=()=>{const s=e(t.proxy);he(t.subTree,s),n(s)};$t(r),xt(()=>{const s=new MutationObserver(r);s.observe(t.subTree.el.parentNode,{childList:!0}),Lt(()=>s.disconnect())})}function he(e,t){if(e.shapeFlag&128){const n=e.suspense;e=n.activeBranch,n.pendingBranch&&!n.isHydrating&&n.effects.push(()=>{he(n.activeBranch,t)})}for(;e.component;)e=e.component.subTree;if(e.shapeFlag&1&&e.el)ve(e.el,t);else if(e.type===it)e.children.forEach(n=>he(n,t));else if(e.type===Dt){let{el:n,anchor:r}=e;for(;n&&(ve(n,t),n!==r);)n=n.nextSibling}}function ve(e,t){if(e.nodeType===1){const n=e.style;for(const r in t)n.setProperty(`--${r}`,t[r])}}const L="transition",W="animation",dt=(e,{slots:t})=>Mt(ot,mt(e),t);dt.displayName="Transition";const pt={name:String,type:String,css:{type:Boolean,default:!0},duration:[String,Number,Object],enterFromClass:String,enterActiveClass:String,enterToClass:String,appearFromClass:String,appearActiveClass:String,appearToClass:String,leaveFromClass:String,leaveActiveClass:String,leaveToClass:String},En=dt.props=G({},ot.props,pt),k=(e,t=[])=>{P(e)?e.forEach(n=>n(...t)):e&&e(...t)},xe=e=>e?P(e)?e.some(t=>t.length>1):e.length>1:!1;function mt(e){const t={};for(const h in e)h in pt||(t[h]=e[h]);if(e.css===!1)return t;const{name:n="v",type:r,duration:s,enterFromClass:o=`${n}-enter-from`,enterActiveClass:i=`${n}-enter-active`,enterToClass:a=`${n}-enter-to`,appearFromClass:l=o,appearActiveClass:c=i,appearToClass:u=a,leaveFromClass:m=`${n}-leave-from`,leaveActiveClass:p=`${n}-leave-active`,leaveToClass:d=`${n}-leave-to`}=e,f=Cn(s),v=f&&f[0],g=f&&f[1],{onBeforeEnter:b,onEnter:S,onEnterCancelled:w,onLeave:I,onLeaveCancelled:x,onBeforeAppear:z=b,onAppear:_=S,onAppearCancelled:E=w}=t,A=(h,O,V)=>{F(h,O?u:a),F(h,O?c:i),V&&V()},we=(h,O)=>{h._isLeaving=!1,F(h,m),F(h,d),F(h,p),O&&O()},Ee=h=>(O,V)=>{const Ce=h?_:S,Oe=()=>A(O,h,V);k(Ce,[O,Oe]),Le(()=>{F(O,h?l:o),R(O,h?u:a),xe(Ce)||Fe(O,r,v,Oe)})};return G(t,{onBeforeEnter(h){k(b,[h]),R(h,o),R(h,i)},onBeforeAppear(h){k(z,[h]),R(h,l),R(h,c)},onEnter:Ee(!1),onAppear:Ee(!0),onLeave(h,O){h._isLeaving=!0;const V=()=>we(h,O);R(h,m),vt(),R(h,p),Le(()=>{h._isLeaving&&(F(h,m),R(h,d),xe(I)||Fe(h,r,g,V))}),k(I,[h,V])},onEnterCancelled(h){A(h,!1),k(w,[h])},onAppearCancelled(h){A(h,!0),k(E,[h])},onLeaveCancelled(h){we(h),k(x,[h])}})}function Cn(e){if(e==null)return null;if(It(e))return[fe(e.enter),fe(e.leave)];{const t=fe(e);return[t,t]}}function fe(e){return de(e)}function R(e,t){t.split(/\s+/).forEach(n=>n&&e.classList.add(n)),(e._vtc||(e._vtc=new Set)).add(t)}function F(e,t){t.split(/\s+/).forEach(r=>r&&e.classList.remove(r));const{_vtc:n}=e;n&&(n.delete(t),n.size||(e._vtc=void 0))}function Le(e){requestAnimationFrame(()=>{requestAnimationFrame(e)})}let On=0;function Fe(e,t,n,r){const s=e._endId=++On,o=()=>{s===e._endId&&r()};if(n)return setTimeout(o,n);const{type:i,timeout:a,propCount:l}=ht(e,t);if(!i)return r();const c=i+"end";let u=0;const m=()=>{e.removeEventListener(c,p),o()},p=d=>{d.target===e&&++u>=l&&m()};setTimeout(()=>{u<l&&m()},a+1),e.addEventListener(c,p)}function ht(e,t){const n=window.getComputedStyle(e),r=f=>(n[f]||"").split(", "),s=r(`${L}Delay`),o=r(`${L}Duration`),i=De(s,o),a=r(`${W}Delay`),l=r(`${W}Duration`),c=De(a,l);let u=null,m=0,p=0;t===L?i>0&&(u=L,m=i,p=o.length):t===W?c>0&&(u=W,m=c,p=l.length):(m=Math.max(i,c),u=m>0?i>c?L:W:null,p=u?u===L?o.length:l.length:0);const d=u===L&&/\b(transform|all)(,|$)/.test(r(`${L}Property`).toString());return{type:u,timeout:m,propCount:p,hasTransform:d}}function De(e,t){for(;e.length<t.length;)e=e.concat(e);return Math.max(...t.map((n,r)=>je(n)+je(e[r])))}function je(e){return Number(e.slice(0,-1).replace(",","."))*1e3}function vt(){return document.body.offsetHeight}const gt=new WeakMap,_t=new WeakMap,bt={name:"TransitionGroup",props:G({},En,{tag:String,moveClass:String}),setup(e,{slots:t}){const n=se(),r=Vt();let s,o;return kt(()=>{if(!s.length)return;const i=e.moveClass||`${e.name||"v"}-move`;if(!Mn(s[0].el,n.vnode.el,i))return;s.forEach(Tn),s.forEach(An);const a=s.filter(Nn);vt(),a.forEach(l=>{const c=l.el,u=c.style;R(c,i),u.transform=u.webkitTransform=u.transitionDuration="";const m=c._moveCb=p=>{p&&p.target!==c||(!p||/transform$/.test(p.propertyName))&&(c.removeEventListener("transitionend",m),c._moveCb=null,F(c,i))};c.addEventListener("transitionend",m)})}),()=>{const i=Bt(e),a=mt(i);let l=i.tag||it;s=o,o=t.default?zt(t.default()):[];for(let c=0;c<o.length;c++){const u=o[c];u.key!=null&&Pe(u,Te(u,a,r,n))}if(s)for(let c=0;c<s.length;c++){const u=s[c];Pe(u,Te(u,a,r,n)),gt.set(u,u.el.getBoundingClientRect())}return lt(l,null,o)}}},Pn=e=>delete e.mode;bt.props;const Tr=bt;function Tn(e){const t=e.el;t._moveCb&&t._moveCb(),t._enterCb&&t._enterCb()}function An(e){_t.set(e,e.el.getBoundingClientRect())}function Nn(e){const t=gt.get(e),n=_t.get(e),r=t.left-n.left,s=t.top-n.top;if(r||s){const o=e.el.style;return o.transform=o.webkitTransform=`translate(${r}px,${s}px)`,o.transitionDuration="0s",e}}function Mn(e,t,n){const r=e.cloneNode();e._vtc&&e._vtc.forEach(i=>{i.split(/\s+/).forEach(a=>a&&r.classList.remove(a))}),n.split(/\s+/).forEach(i=>i&&r.classList.add(i)),r.style.display="none";const s=t.nodeType===1?t:t.parentNode;s.appendChild(r);const{hasTransform:o}=ht(r);return s.removeChild(r),o}const j=e=>{const t=e.props["onUpdate:modelValue"]||!1;return P(t)?n=>Ft(t,n):t};function In(e){e.target.composing=!0}function Ve(e){const t=e.target;t.composing&&(t.composing=!1,t.dispatchEvent(new Event("input")))}const ge={created(e,{modifiers:{lazy:t,trim:n,number:r}},s){e._assign=j(s);const o=r||s.props&&s.props.type==="number";$(e,t?"change":"input",i=>{if(i.target.composing)return;let a=e.value;n&&(a=a.trim()),o&&(a=pe(a)),e._assign(a)}),n&&$(e,"change",()=>{e.value=e.value.trim()}),t||($(e,"compositionstart",In),$(e,"compositionend",Ve),$(e,"change",Ve))},mounted(e,{value:t}){e.value=t??""},beforeUpdate(e,{value:t,modifiers:{lazy:n,trim:r,number:s}},o){if(e._assign=j(o),e.composing||document.activeElement===e&&e.type!=="range"&&(n||r&&e.value.trim()===t||(s||e.type==="number")&&pe(e.value)===t))return;const i=t??"";e.value!==i&&(e.value=i)}},yt={deep:!0,created(e,t,n){e._assign=j(n),$(e,"change",()=>{const r=e._modelValue,s=U(e),o=e.checked,i=e._assign;if(P(r)){const a=oe(r,s),l=a!==-1;if(o&&!l)i(r.concat(s));else if(!o&&l){const c=[...r];c.splice(a,1),i(c)}}else if(X(r)){const a=new Set(r);o?a.add(s):a.delete(s),i(a)}else i(wt(e,o))})},mounted:ke,beforeUpdate(e,t,n){e._assign=j(n),ke(e,t,n)}};function ke(e,{value:t,oldValue:n},r){e._modelValue=t,P(t)?e.checked=oe(t,r.props.value)>-1:X(t)?e.checked=t.has(r.props.value):t!==n&&(e.checked=K(t,wt(e,!0)))}const St={created(e,{value:t},n){e.checked=K(t,n.props.value),e._assign=j(n),$(e,"change",()=>{e._assign(U(e))})},beforeUpdate(e,{value:t,oldValue:n},r){e._assign=j(r),t!==n&&(e.checked=K(t,r.props.value))}},Rn={deep:!0,created(e,{value:t,modifiers:{number:n}},r){const s=X(t);$(e,"change",()=>{const o=Array.prototype.filter.call(e.options,i=>i.selected).map(i=>n?pe(U(i)):U(i));e._assign(e.multiple?s?new Set(o):o:o[0])}),e._assign=j(r)},mounted(e,{value:t}){Be(e,t)},beforeUpdate(e,t,n){e._assign=j(n)},updated(e,{value:t}){Be(e,t)}};function Be(e,t){const n=e.multiple;if(!(n&&!P(t)&&!X(t))){for(let r=0,s=e.options.length;r<s;r++){const o=e.options[r],i=U(o);if(n)P(t)?o.selected=oe(t,i)>-1:o.selected=t.has(i);else if(K(U(o),t)){e.selectedIndex!==r&&(e.selectedIndex=r);return}}!n&&e.selectedIndex!==-1&&(e.selectedIndex=-1)}}function U(e){return"_value"in e?e._value:e.value}function wt(e,t){const n=t?"_trueValue":"_falseValue";return n in e?e[n]:t}const $n={created(e,t,n){ee(e,t,n,null,"created")},mounted(e,t,n){ee(e,t,n,null,"mounted")},beforeUpdate(e,t,n,r){ee(e,t,n,r,"beforeUpdate")},updated(e,t,n,r){ee(e,t,n,r,"updated")}};function Et(e,t){switch(e){case"SELECT":return Rn;case"TEXTAREA":return ge;default:switch(t){case"checkbox":return yt;case"radio":return St;default:return ge}}}function ee(e,t,n,r,s){const i=Et(e.tagName,n.props&&n.props.type)[s];i&&i(e,t,n,r)}function xn(){ge.getSSRProps=({value:e})=>({value:e}),St.getSSRProps=({value:e},t)=>{if(t.props&&K(t.props.value,e))return{checked:!0}},yt.getSSRProps=({value:e},t)=>{if(P(e)){if(t.props&&oe(e,t.props.value)>-1)return{checked:!0}}else if(X(e)){if(t.props&&e.has(t.props.value))return{checked:!0}}else if(e)return{checked:!0}},$n.getSSRProps=(e,t)=>{if(typeof t.type!="string")return;const n=Et(t.type.toUpperCase(),t.props&&t.props.type);if(n.getSSRProps)return n.getSSRProps(e,t)}}const Ln=["ctrl","shift","alt","meta"],Fn={stop:e=>e.stopPropagation(),prevent:e=>e.preventDefault(),self:e=>e.target!==e.currentTarget,ctrl:e=>!e.ctrlKey,shift:e=>!e.shiftKey,alt:e=>!e.altKey,meta:e=>!e.metaKey,left:e=>"button"in e&&e.button!==0,middle:e=>"button"in e&&e.button!==1,right:e=>"button"in e&&e.button!==2,exact:(e,t)=>Ln.some(n=>e[`${n}Key`]&&!t.includes(n))},Ar=(e,t)=>(n,...r)=>{for(let s=0;s<t.length;s++){const o=Fn[t[s]];if(o&&o(n,t))return}return e(n,...r)},Dn={esc:"escape",space:" ",up:"arrow-up",left:"arrow-left",right:"arrow-right",down:"arrow-down",delete:"backspace"},Nr=(e,t)=>n=>{if(!("key"in n))return;const r=D(n.key);if(t.some(s=>s===r||Dn[s]===r))return e(n)},jn={beforeMount(e,{value:t},{transition:n}){e._vod=e.style.display==="none"?"":e.style.display,n&&t?n.beforeEnter(e):q(e,t)},mounted(e,{value:t},{transition:n}){n&&t&&n.enter(e)},updated(e,{value:t,oldValue:n},{transition:r}){!t!=!n&&(r?t?(r.beforeEnter(e),q(e,!0),r.enter(e)):r.leave(e,()=>{q(e,!1)}):q(e,t))},beforeUnmount(e,{value:t}){q(e,t)}};function q(e,t){e.style.display=t?e._vod:"none"}function Vn(){jn.getSSRProps=({value:e})=>{if(!e)return{style:{display:"none"}}}}const Ct=G({patchProp:bn},on);let Q,ze=!1;function Ot(){return Q||(Q=Rt(Ct))}function Pt(){return Q=ze?Q:Wt(Ct),ze=!0,Q}const He=(...e)=>{Ot().render(...e)},kn=(...e)=>{Pt().hydrate(...e)},Mr=(...e)=>{const t=Ot().createApp(...e),{mount:n}=t;return t.mount=r=>{const s=Tt(r);if(!s)return;const o=t._component;!st(o)&&!o.render&&!o.template&&(o.template=s.innerHTML),s.innerHTML="";const i=n(s,!1,s instanceof SVGElement);return s instanceof Element&&(s.removeAttribute("v-cloak"),s.setAttribute("data-v-app","")),i},t},Ir=(...e)=>{const t=Pt().createApp(...e),{mount:n}=t;return t.mount=r=>{const s=Tt(r);if(s)return n(s,!0,s instanceof SVGElement)},t};function Tt(e){return ne(e)?document.querySelector(e):e}let Ue=!1;const Rr=()=>{Ue||(Ue=!0,xn(),Vn())};function N(e){var t;const n=J(e);return(t=n==null?void 0:n.$el)!=null?t:n}const T=ie?window:void 0,Se=ie?window.document:void 0,Bn=ie?window.navigator:void 0;ie&&window.location;function C(...e){let t,n,r,s;if(ft(e[0])||Array.isArray(e[0])?([n,r,s]=e,t=T):[t,n,r,s]=e,!t)return en;Array.isArray(n)||(n=[n]),Array.isArray(r)||(r=[r]);const o=[],i=()=>{o.forEach(u=>u()),o.length=0},a=(u,m,p)=>(u.addEventListener(m,p,s),()=>u.removeEventListener(m,p,s)),l=M(()=>N(t),u=>{i(),u&&o.push(...n.flatMap(m=>r.map(p=>a(u,m,p))))},{immediate:!0,flush:"post"}),c=()=>{l(),i()};return Y(c),c}function $r(e,t,n={}){const{window:r=T,ignore:s=[],capture:o=!0,detectIframe:i=!1}=n;if(!r)return;let a=!0,l;const c=d=>s.some(f=>{if(typeof f=="string")return Array.from(r.document.querySelectorAll(f)).some(v=>v===d.target||d.composedPath().includes(v));{const v=N(f);return v&&(d.target===v||d.composedPath().includes(v))}}),u=d=>{r.clearTimeout(l);const f=N(e);if(!(!f||f===d.target||d.composedPath().includes(f))){if(d.detail===0&&(a=!c(d)),!a){a=!0;return}t(d)}},m=[C(r,"click",u,{passive:!0,capture:o}),C(r,"pointerdown",d=>{const f=N(e);f&&(a=!d.composedPath().includes(f)&&!c(d))},{passive:!0}),C(r,"pointerup",d=>{if(d.button===0){const f=d.composedPath();d.composedPath=()=>f,l=r.setTimeout(()=>u(d),50)}},{passive:!0}),i&&C(r,"blur",d=>{var f;const v=N(e);((f=r.document.activeElement)==null?void 0:f.tagName)==="IFRAME"&&!(v!=null&&v.contains(r.document.activeElement))&&t(d)})].filter(Boolean);return()=>m.forEach(d=>d())}function Z(e,t=!1){const n=y(),r=()=>n.value=Boolean(e());return r(),ae(r,t),n}function zn(e,t={}){const{window:n=T}=t,r=Z(()=>n&&"matchMedia"in n&&typeof n.matchMedia=="function");let s;const o=y(!1),i=()=>{s&&("removeEventListener"in s?s.removeEventListener("change",a):s.removeListener(a))},a=()=>{r.value&&(i(),s=n.matchMedia(ut(e).value),o.value=s.matches,"addEventListener"in s?s.addEventListener("change",a):s.addListener(a))};return Gt(a),Y(()=>i()),o}function xr(e={}){const{navigator:t=Bn,read:n=!1,source:r,copiedDuring:s=1500,legacy:o=!1}=e,i=["copy","cut"],a=Z(()=>t&&"clipboard"in t),l=H(()=>a.value||o),c=y(""),u=y(!1),m=nn(()=>u.value=!1,s);function p(){a.value?t.clipboard.readText().then(g=>{c.value=g}):c.value=v()}if(l.value&&n)for(const g of i)C(g,p);async function d(g=J(r)){l.value&&g!=null&&(a.value?await t.clipboard.writeText(g):f(g),c.value=g,u.value=!0,m.start())}function f(g){const b=document.createElement("textarea");b.value=g??"",b.style.position="absolute",b.style.opacity="0",document.body.appendChild(b),b.select(),document.execCommand("copy"),b.remove()}function v(){var g,b,S;return(S=(b=(g=document==null?void 0:document.getSelection)==null?void 0:g.call(document))==null?void 0:b.toString())!=null?S:""}return{isSupported:l,text:c,copied:u,copy:d}}function Hn(e){return JSON.parse(JSON.stringify(e))}const _e=typeof globalThis<"u"?globalThis:typeof window<"u"?window:typeof global<"u"?global:typeof self<"u"?self:{},be="__vueuse_ssr_handlers__";_e[be]=_e[be]||{};const Un=_e[be];function At(e,t){return Un[e]||t}function Wn(e){return e==null?"any":e instanceof Set?"set":e instanceof Map?"map":e instanceof Date?"date":typeof e=="boolean"?"boolean":typeof e=="string"?"string":typeof e=="object"?"object":Number.isNaN(e)?"any":"number"}var qn=Object.defineProperty,We=Object.getOwnPropertySymbols,Qn=Object.prototype.hasOwnProperty,Kn=Object.prototype.propertyIsEnumerable,qe=(e,t,n)=>t in e?qn(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n,Qe=(e,t)=>{for(var n in t||(t={}))Qn.call(t,n)&&qe(e,n,t[n]);if(We)for(var n of We(t))Kn.call(t,n)&&qe(e,n,t[n]);return e};const Jn={boolean:{read:e=>e==="true",write:e=>String(e)},object:{read:e=>JSON.parse(e),write:e=>JSON.stringify(e)},number:{read:e=>Number.parseFloat(e),write:e=>String(e)},any:{read:e=>e,write:e=>String(e)},string:{read:e=>e,write:e=>String(e)},map:{read:e=>new Map(JSON.parse(e)),write:e=>JSON.stringify(Array.from(e.entries()))},set:{read:e=>new Set(JSON.parse(e)),write:e=>JSON.stringify(Array.from(e))},date:{read:e=>new Date(e),write:e=>e.toISOString()}};function Gn(e,t,n,r={}){var s;const{flush:o="pre",deep:i=!0,listenToStorageChanges:a=!0,writeDefaults:l=!0,mergeDefaults:c=!1,shallow:u,window:m=T,eventFilter:p,onError:d=_=>{console.error(_)}}=r,f=(u?Xt:y)(t);if(!n)try{n=At("getDefaultStorage",()=>{var _;return(_=T)==null?void 0:_.localStorage})()}catch(_){d(_)}if(!n)return f;const v=J(t),g=Wn(v),b=(s=r.serializer)!=null?s:Jn[g],{pause:S,resume:w}=tn(f,()=>I(f.value),{flush:o,deep:i,eventFilter:p});return m&&a&&C(m,"storage",z),z(),f;function I(_){try{if(_==null)n.removeItem(e);else{const E=b.write(_),A=n.getItem(e);A!==E&&(n.setItem(e,E),m&&(m==null||m.dispatchEvent(new StorageEvent("storage",{key:e,oldValue:A,newValue:E,storageArea:n}))))}}catch(E){d(E)}}function x(_){const E=_?_.newValue:n.getItem(e);if(E==null)return l&&v!==null&&n.setItem(e,b.write(v)),v;if(!_&&c){const A=b.read(E);return re(c)?c(A,v):g==="object"&&!Array.isArray(A)?Qe(Qe({},v),A):A}else return typeof E!="string"?E:b.read(E)}function z(_){if(!(_&&_.storageArea!==n)){if(_&&_.key==null){f.value=v;return}if(!(_&&_.key!==e)){S();try{f.value=x(_)}catch(E){d(E)}finally{_?at(w):w()}}}}}function Nt(e){return zn("(prefers-color-scheme: dark)",e)}var Xn=Object.defineProperty,Ke=Object.getOwnPropertySymbols,Yn=Object.prototype.hasOwnProperty,Zn=Object.prototype.propertyIsEnumerable,Je=(e,t,n)=>t in e?Xn(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n,er=(e,t)=>{for(var n in t||(t={}))Yn.call(t,n)&&Je(e,n,t[n]);if(Ke)for(var n of Ke(t))Zn.call(t,n)&&Je(e,n,t[n]);return e};function tr(e={}){const{selector:t="html",attribute:n="class",initialValue:r="auto",window:s=T,storage:o,storageKey:i="vueuse-color-scheme",listenToStorageChanges:a=!0,storageRef:l,emitAuto:c}=e,u=er({auto:"",light:"light",dark:"dark"},e.modes||{}),m=Nt({window:s}),p=H(()=>m.value?"dark":"light"),d=l||(i==null?y(r):Gn(i,r,o,{window:s,listenToStorageChanges:a})),f=H({get(){return d.value==="auto"&&!c?p.value:d.value},set(S){d.value=S}}),v=At("updateHTMLAttrs",(S,w,I)=>{const x=s==null?void 0:s.document.querySelector(S);if(x)if(w==="class"){const z=I.split(/\s/g);Object.values(u).flatMap(_=>(_||"").split(/\s/g)).filter(Boolean).forEach(_=>{z.includes(_)?x.classList.add(_):x.classList.remove(_)})}else x.setAttribute(w,I)});function g(S){var w;const I=S==="auto"?p.value:S;v(t,n,(w=u[I])!=null?w:I)}function b(S){e.onChanged?e.onChanged(S,g):g(S)}return M(f,b,{flush:"post",immediate:!0}),c&&M(p,()=>b(f.value),{flush:"post"}),ae(()=>b(f.value)),f}function Lr(e,t,{window:n=T,initialValue:r=""}={}){const s=y(r),o=H(()=>{var i;return N(t)||((i=n==null?void 0:n.document)==null?void 0:i.documentElement)});return M([o,()=>J(e)],([i,a])=>{var l;if(i&&n){const c=(l=n.getComputedStyle(i).getPropertyValue(a))==null?void 0:l.trim();s.value=c||r}},{immediate:!0}),M(s,i=>{var a;(a=o.value)!=null&&a.style&&o.value.style.setProperty(J(e),i)}),s}var nr=Object.defineProperty,rr=Object.defineProperties,sr=Object.getOwnPropertyDescriptors,Ge=Object.getOwnPropertySymbols,or=Object.prototype.hasOwnProperty,ir=Object.prototype.propertyIsEnumerable,Xe=(e,t,n)=>t in e?nr(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n,ar=(e,t)=>{for(var n in t||(t={}))or.call(t,n)&&Xe(e,n,t[n]);if(Ge)for(var n of Ge(t))ir.call(t,n)&&Xe(e,n,t[n]);return e},lr=(e,t)=>rr(e,sr(t));function Fr(e={}){const{valueDark:t="dark",valueLight:n="",window:r=T}=e,s=tr(lr(ar({},e),{onChanged:(a,l)=>{var c;e.onChanged?(c=e.onChanged)==null||c.call(e,a==="dark"):l(a)},modes:{dark:t,light:n}})),o=Nt({window:r});return H({get(){return s.value==="dark"},set(a){a===o.value?s.value="auto":s.value=a?"dark":"light"}})}function Dr({document:e=Se}={}){if(!e)return y("visible");const t=y(e.visibilityState);return C(e,"visibilitychange",()=>{t.value=e.visibilityState}),t}var Ye=Object.getOwnPropertySymbols,cr=Object.prototype.hasOwnProperty,ur=Object.prototype.propertyIsEnumerable,fr=(e,t)=>{var n={};for(var r in e)cr.call(e,r)&&t.indexOf(r)<0&&(n[r]=e[r]);if(e!=null&&Ye)for(var r of Ye(e))t.indexOf(r)<0&&ur.call(e,r)&&(n[r]=e[r]);return n};function dr(e,t,n={}){const r=n,{window:s=T}=r,o=fr(r,["window"]);let i;const a=Z(()=>s&&"ResizeObserver"in s),l=()=>{i&&(i.disconnect(),i=void 0)},c=M(()=>N(e),m=>{l(),a.value&&s&&m&&(i=new ResizeObserver(t),i.observe(m,o))},{immediate:!0,flush:"post"}),u=()=>{l(),c()};return Y(u),{isSupported:a,stop:u}}function jr(e,t={}){const{reset:n=!0,windowResize:r=!0,windowScroll:s=!0,immediate:o=!0}=t,i=y(0),a=y(0),l=y(0),c=y(0),u=y(0),m=y(0),p=y(0),d=y(0);function f(){const v=N(e);if(!v){n&&(i.value=0,a.value=0,l.value=0,c.value=0,u.value=0,m.value=0,p.value=0,d.value=0);return}const g=v.getBoundingClientRect();i.value=g.height,a.value=g.bottom,l.value=g.left,c.value=g.right,u.value=g.top,m.value=g.width,p.value=g.x,d.value=g.y}return dr(e,f),M(()=>N(e),v=>!v&&f()),s&&C("scroll",f,{capture:!0,passive:!0}),r&&C("resize",f,{passive:!0}),ae(()=>{o&&f()}),{height:i,bottom:a,left:l,right:c,top:u,width:m,x:p,y:d,update:f}}const Ze=[["requestFullscreen","exitFullscreen","fullscreenElement","fullscreenEnabled","fullscreenchange","fullscreenerror"],["webkitRequestFullscreen","webkitExitFullscreen","webkitFullscreenElement","webkitFullscreenEnabled","webkitfullscreenchange","webkitfullscreenerror"],["webkitRequestFullScreen","webkitCancelFullScreen","webkitCurrentFullScreenElement","webkitCancelFullScreen","webkitfullscreenchange","webkitfullscreenerror"],["mozRequestFullScreen","mozCancelFullScreen","mozFullScreenElement","mozFullScreenEnabled","mozfullscreenchange","mozfullscreenerror"],["msRequestFullscreen","msExitFullscreen","msFullscreenElement","msFullscreenEnabled","MSFullscreenChange","MSFullscreenError"]];function Vr(e,t={}){const{document:n=Se,autoExit:r=!1}=t,s=e||(n==null?void 0:n.querySelector("html")),o=y(!1);let i=Ze[0];const a=Z(()=>{if(n){for(const v of Ze)if(v[1]in n)return i=v,!0}else return!1;return!1}),[l,c,u,,m]=i;async function p(){a.value&&(n!=null&&n[u]&&await n[c](),o.value=!1)}async function d(){if(!a.value)return;await p();const v=N(s);v&&(await v[l](),o.value=!0)}async function f(){o.value?await p():await d()}return n&&C(n,m,()=>{o.value=!!(n!=null&&n[u])},!1),r&&Y(p),{isSupported:a,isFullscreen:o,enter:d,exit:p,toggle:f}}var et=Object.getOwnPropertySymbols,pr=Object.prototype.hasOwnProperty,mr=Object.prototype.propertyIsEnumerable,hr=(e,t)=>{var n={};for(var r in e)pr.call(e,r)&&t.indexOf(r)<0&&(n[r]=e[r]);if(e!=null&&et)for(var r of et(e))t.indexOf(r)<0&&mr.call(e,r)&&(n[r]=e[r]);return n};function vr(e,t,n={}){const r=n,{window:s=T}=r,o=hr(r,["window"]);let i;const a=Z(()=>s&&"MutationObserver"in s),l=()=>{i&&(i.disconnect(),i=void 0)},c=M(()=>N(e),m=>{l(),a.value&&s&&m&&(i=new MutationObserver(t),i.observe(m,o))},{immediate:!0}),u=()=>{l(),c()};return Y(u),{isSupported:a,stop:u}}var tt;(function(e){e.UP="UP",e.RIGHT="RIGHT",e.DOWN="DOWN",e.LEFT="LEFT",e.NONE="NONE"})(tt||(tt={}));function kr(){const e=y([]);return e.value.set=t=>{t&&e.value.push(t)},Yt(()=>{e.value.length=0}),e}function Br(e=null,t={}){var n,r;const{document:s=Se}=t,o=ut((n=e??(s==null?void 0:s.title))!=null?n:null),i=e&&re(e);function a(l){if(!("titleTemplate"in t))return l;const c=t.titleTemplate||"%s";return re(c)?c(l):Jt(c).replace(/%s/g,l)}return M(o,(l,c)=>{l!==c&&s&&(s.title=a(ft(l)?l:""))},{immediate:!0}),t.observe&&!t.titleTemplate&&s&&!i&&vr((r=s.head)==null?void 0:r.querySelector("title"),()=>{s&&s.title!==o.value&&(o.value=a(s.title))},{childList:!0}),o}var gr=Object.defineProperty,nt=Object.getOwnPropertySymbols,_r=Object.prototype.hasOwnProperty,br=Object.prototype.propertyIsEnumerable,rt=(e,t,n)=>t in e?gr(e,t,{enumerable:!0,configurable:!0,writable:!0,value:n}):e[t]=n,yr=(e,t)=>{for(var n in t||(t={}))_r.call(t,n)&&rt(e,n,t[n]);if(nt)for(var n of nt(t))br.call(t,n)&&rt(e,n,t[n]);return e};const Sr={easeInSine:[.12,0,.39,0],easeOutSine:[.61,1,.88,1],easeInOutSine:[.37,0,.63,1],easeInQuad:[.11,0,.5,0],easeOutQuad:[.5,1,.89,1],easeInOutQuad:[.45,0,.55,1],easeInCubic:[.32,0,.67,0],easeOutCubic:[.33,1,.68,1],easeInOutCubic:[.65,0,.35,1],easeInQuart:[.5,0,.75,0],easeOutQuart:[.25,1,.5,1],easeInOutQuart:[.76,0,.24,1],easeInQuint:[.64,0,.78,0],easeOutQuint:[.22,1,.36,1],easeInOutQuint:[.83,0,.17,1],easeInExpo:[.7,0,.84,0],easeOutExpo:[.16,1,.3,1],easeInOutExpo:[.87,0,.13,1],easeInCirc:[.55,0,1,.45],easeOutCirc:[0,.55,.45,1],easeInOutCirc:[.85,0,.15,1],easeInBack:[.36,0,.66,-.56],easeOutBack:[.34,1.56,.64,1],easeInOutBack:[.68,-.6,.32,1.6]};yr({linear:Zt},Sr);function zr(e,t,n,r={}){var s,o,i;const{clone:a=!1,passive:l=!1,eventName:c,deep:u=!1,defaultValue:m}=r,p=se(),d=n||(p==null?void 0:p.emit)||((s=p==null?void 0:p.$emit)==null?void 0:s.bind(p))||((i=(o=p==null?void 0:p.proxy)==null?void 0:o.$emit)==null?void 0:i.bind(p==null?void 0:p.proxy));let f=c;t||(t="modelValue"),f=c||f||`update:${t.toString()}`;const v=b=>a?re(a)?a(b):Hn(b):b,g=()=>rn(e[t])?v(e[t]):m;if(l){const b=g(),S=y(b);return M(()=>e[t],w=>S.value=v(w)),M(S,w=>{(w!==e[t]||u)&&d(f,w)},{deep:u}),S}else return H({get(){return g()},set(b){d(f,b)}})}function Hr({window:e=T}={}){if(!e)return y(!1);const t=y(e.document.hasFocus());return C(e,"blur",()=>{t.value=!1}),C(e,"focus",()=>{t.value=!0}),t}function Ur(e={}){const{window:t=T,initialWidth:n=1/0,initialHeight:r=1/0,listenOrientation:s=!0,includeScrollbar:o=!0}=e,i=y(n),a=y(r),l=()=>{t&&(o?(i.value=t.innerWidth,a.value=t.innerHeight):(i.value=t.document.documentElement.clientWidth,a.value=t.document.documentElement.clientHeight))};return l(),ae(l),C("resize",l,{passive:!0}),s&&C("orientationchange",l,{passive:!0}),{width:i,height:a}}export{Rr as A,Or as B,$n as C,Rn as D,xr as E,kr as F,Vr as G,dt as T,ye as V,Ur as a,jr as b,C as c,Ar as d,dr as e,Lr as f,ge as g,Fr as h,Mr as i,Br as j,yt as k,Tr as l,zr as m,St as n,$r as o,Dr as p,Hr as q,He as r,Pr as s,Ir as t,N as u,jn as v,Nr as w,Sn as x,Cr as y,kn as z};