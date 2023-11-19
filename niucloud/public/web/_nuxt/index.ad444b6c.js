import{L as K,u as k,M as X,ag as vt,O as Z,ax as yt,x as C,B as Zt,f as Yt,P as te,r as ee,ap as wt,Q as ne,__tla as re}from"./entry.9b92f05f.js";let xt,Y,Mt,kt,Et,St,tt,At,Rt,ae=Promise.all([(()=>{try{return re}catch{}})()]).then(async()=>{tt=function(t){return t==null};class Ft extends Error{constructor(e){super(e),this.name="ElementPlusError"}}At=function(t,e){throw new Ft(`[${t}] ${e}`)},Et=function(t,e){},Rt=({from:t,replacement:e,scope:n,version:r,ref:a,type:o="API"},s)=>{K(()=>k(s),c=>{},{immediate:!0})};let x=[],O,et,E,S,nt,W,rt,j,$,A,M,R;O=t=>{const e=t;e.key===yt.esc&&x.forEach(n=>n(e))},et=t=>{X(()=>{x.length===0&&document.addEventListener("keydown",O),vt&&x.push(t)}),Z(()=>{x=x.filter(e=>e!==t),x.length===0&&vt&&document.removeEventListener("keydown",O)})},St=Symbol("formContextKey"),kt=Symbol("formItemContextKey"),E="focus-trap.focus-after-trapped",S="focus-trap.focus-after-released",nt="focus-trap.focusout-prevented",W={cancelable:!0,bubbles:!1},rt={cancelable:!0,bubbles:!1},j="focusAfterTrapped",$="focusAfterReleased",Y=Symbol("elFocusTrap"),A=C(),M=C(0),R=C(0);let F=0;const at=t=>{const e=[],n=document.createTreeWalker(t,NodeFilter.SHOW_ELEMENT,{acceptNode:r=>{const a=r.tagName==="INPUT"&&r.type==="hidden";return r.disabled||r.hidden||a?NodeFilter.FILTER_SKIP:r.tabIndex>=0||r===document.activeElement?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_SKIP}});for(;n.nextNode();)e.push(n.currentNode);return e},ot=(t,e)=>{for(const n of t)if(!Ht(n,e))return n},Ht=(t,e)=>{if(getComputedStyle(t).visibility==="hidden")return!0;for(;t;){if(e&&t===e)return!1;if(getComputedStyle(t).display==="none")return!0;t=t.parentElement}return!1},Lt=t=>{const e=at(t),n=ot(e,t),r=ot(e.reverse(),t);return[n,r]},Tt=t=>t instanceof HTMLInputElement&&"select"in t,m=(t,e)=>{if(t&&t.focus){const n=document.activeElement;t.focus({preventScroll:!0}),R.value=window.performance.now(),t!==n&&Tt(t)&&e&&t.select()}};function st(t,e){const n=[...t],r=t.indexOf(e);return r!==-1&&n.splice(r,1),n}const Nt=()=>{let t=[];return{push:e=>{const n=t[0];n&&e!==n&&n.pause(),t=st(t,e),t.unshift(e)},remove:e=>{var n,r;t=st(t,e),(r=(n=t[0])==null?void 0:n.resume)==null||r.call(n)}}},Pt=(t,e=!1)=>{const n=document.activeElement;for(const r of t)if(m(r,e),document.activeElement!==n)return},it=Nt(),It=()=>M.value>R.value,H=()=>{A.value="pointer",M.value=window.performance.now()},ct=()=>{A.value="keyboard",M.value=window.performance.now()},_t=()=>(X(()=>{F===0&&(document.addEventListener("mousedown",H),document.addEventListener("touchstart",H),document.addEventListener("keydown",ct)),F++}),Z(()=>{F--,F<=0&&(document.removeEventListener("mousedown",H),document.removeEventListener("touchstart",H),document.removeEventListener("keydown",ct))}),{focusReason:A,lastUserFocusTimestamp:M,lastAutomatedFocusTimestamp:R}),L=t=>new CustomEvent(nt,{...rt,detail:t}),qt=Yt({name:"ElFocusTrap",inheritAttrs:!1,props:{loop:Boolean,trapped:Boolean,focusTrapEl:Object,focusStartEl:{type:[Object,String],default:"first"}},emits:[j,$,"focusin","focusout","focusout-prevented","release-requested"],setup(t,{emit:e}){const n=C();let r,a;const{focusReason:o}=_t();et(i=>{t.trapped&&!s.paused&&e("release-requested",i)});const s={paused:!1,pause(){this.paused=!0},resume(){this.paused=!1}},c=i=>{if(!t.loop&&!t.trapped||s.paused)return;const{key:u,altKey:l,ctrlKey:d,metaKey:_,currentTarget:Qt,shiftKey:bt}=i,{loop:mt}=t,Xt=u===yt.tab&&!l&&!d&&!_,q=document.activeElement;if(Xt&&q){const G=Qt,[J,Q]=Lt(G);if(J&&Q){if(!bt&&q===Q){const y=L({focusReason:o.value});e("focusout-prevented",y),y.defaultPrevented||(i.preventDefault(),mt&&m(J,!0))}else if(bt&&[J,G].includes(q)){const y=L({focusReason:o.value});e("focusout-prevented",y),y.defaultPrevented||(i.preventDefault(),mt&&m(Q,!0))}}else if(q===G){const y=L({focusReason:o.value});e("focusout-prevented",y),y.defaultPrevented||i.preventDefault()}}};te(Y,{focusTrapRef:n,onKeydown:c}),K(()=>t.focusTrapEl,i=>{i&&(n.value=i)},{immediate:!0}),K([n],([i],[u])=>{i&&(i.addEventListener("keydown",c),i.addEventListener("focusin",P),i.addEventListener("focusout",I)),u&&(u.removeEventListener("keydown",c),u.removeEventListener("focusin",P),u.removeEventListener("focusout",I))});const h=i=>{e(j,i)},V=i=>e($,i),P=i=>{const u=k(n);if(!u)return;const l=i.target,d=i.relatedTarget,_=l&&u.contains(l);t.trapped||d&&u.contains(d)||(r=d),_&&e("focusin",i),!s.paused&&t.trapped&&(_?a=l:m(a,!0))},I=i=>{const u=k(n);if(!(s.paused||!u))if(t.trapped){const l=i.relatedTarget;!tt(l)&&!u.contains(l)&&setTimeout(()=>{if(!s.paused&&t.trapped){const d=L({focusReason:o.value});e("focusout-prevented",d),d.defaultPrevented||m(a,!0)}},0)}else{const l=i.target;l&&u.contains(l)||e("focusout",i)}};async function pt(){await wt();const i=k(n);if(i){it.push(s);const u=i.contains(document.activeElement)?r:document.activeElement;if(r=u,!i.contains(u)){const l=new Event(E,W);i.addEventListener(E,h),i.dispatchEvent(l),l.defaultPrevented||wt(()=>{let d=t.focusStartEl;ne(d)||(m(d),document.activeElement!==d&&(d="first")),d==="first"&&Pt(at(i),!0),(document.activeElement===u||d==="container")&&m(i)})}}}function gt(){const i=k(n);if(i){i.removeEventListener(E,h);const u=new CustomEvent(S,{...W,detail:{focusReason:o.value}});i.addEventListener(S,V),i.dispatchEvent(u),!u.defaultPrevented&&(o.value=="keyboard"||!It()||i.contains(document.activeElement))&&m(r??document.body),i.removeEventListener(S,h),it.remove(s)}}return X(()=>{t.trapped&&pt(),K(()=>t.trapped,i=>{i?pt():gt()})}),Z(()=>{t.trapped&&gt()}),{onKeydown:c}}});function Kt(t,e,n,r,a,o){return ee(t.$slots,"default",{handleKeydown:t.onKeydown})}xt=Zt(qt,[["render",Kt],["__file","/home/runner/work/element-plus/element-plus/packages/components/focus-trap/src/focus-trap.vue"]]);function f(t,e){Ct(t)&&(t="100%");var n=Ot(t);return t=e===360?t:Math.min(e,Math.max(0,parseFloat(t))),n&&(t=parseInt(String(t*e),10)/100),Math.abs(t-e)<1e-6?1:(e===360?t=(t<0?t%e+e:t%e)/parseFloat(String(e)):t=t%e/parseFloat(String(e)),t)}function T(t){return Math.min(1,Math.max(0,t))}function Ct(t){return typeof t=="string"&&t.indexOf(".")!==-1&&parseFloat(t)===1}function Ot(t){return typeof t=="string"&&t.indexOf("%")!==-1}function ut(t){return t=parseFloat(t),(isNaN(t)||t<0||t>1)&&(t=1),t}function N(t){return t<=1?"".concat(Number(t)*100,"%"):t}function w(t){return t.length===1?"0"+t:String(t)}function Wt(t,e,n){return{r:f(t,255)*255,g:f(e,255)*255,b:f(n,255)*255}}function ft(t,e,n){t=f(t,255),e=f(e,255),n=f(n,255);var r=Math.max(t,e,n),a=Math.min(t,e,n),o=0,s=0,c=(r+a)/2;if(r===a)s=0,o=0;else{var h=r-a;switch(s=c>.5?h/(2-r-a):h/(r+a),r){case t:o=(e-n)/h+(e<n?6:0);break;case e:o=(n-t)/h+2;break;case n:o=(t-e)/h+4;break}o/=6}return{h:o,s,l:c}}function B(t,e,n){return n<0&&(n+=1),n>1&&(n-=1),n<1/6?t+(e-t)*(6*n):n<1/2?e:n<2/3?t+(e-t)*(2/3-n)*6:t}function jt(t,e,n){var r,a,o;if(t=f(t,360),e=f(e,100),n=f(n,100),e===0)a=n,o=n,r=n;else{var s=n<.5?n*(1+e):n+e-n*e,c=2*n-s;r=B(c,s,t+1/3),a=B(c,s,t),o=B(c,s,t-1/3)}return{r:r*255,g:a*255,b:o*255}}function ht(t,e,n){t=f(t,255),e=f(e,255),n=f(n,255);var r=Math.max(t,e,n),a=Math.min(t,e,n),o=0,s=r,c=r-a,h=r===0?0:c/r;if(r===a)o=0;else{switch(r){case t:o=(e-n)/c+(e<n?6:0);break;case e:o=(n-t)/c+2;break;case n:o=(t-e)/c+4;break}o/=6}return{h:o,s:h,v:s}}function $t(t,e,n){t=f(t,360)*6,e=f(e,100),n=f(n,100);var r=Math.floor(t),a=t-r,o=n*(1-e),s=n*(1-a*e),c=n*(1-(1-a)*e),h=r%6,V=[n,s,o,o,c,n][h],P=[c,n,n,s,o,o][h],I=[o,o,c,n,n,s][h];return{r:V*255,g:P*255,b:I*255}}function dt(t,e,n,r){var a=[w(Math.round(t).toString(16)),w(Math.round(e).toString(16)),w(Math.round(n).toString(16))];return r&&a[0].startsWith(a[0].charAt(1))&&a[1].startsWith(a[1].charAt(1))&&a[2].startsWith(a[2].charAt(1))?a[0].charAt(0)+a[1].charAt(0)+a[2].charAt(0):a.join("")}function Bt(t,e,n,r,a){var o=[w(Math.round(t).toString(16)),w(Math.round(e).toString(16)),w(Math.round(n).toString(16)),w(Dt(r))];return a&&o[0].startsWith(o[0].charAt(1))&&o[1].startsWith(o[1].charAt(1))&&o[2].startsWith(o[2].charAt(1))&&o[3].startsWith(o[3].charAt(1))?o[0].charAt(0)+o[1].charAt(0)+o[2].charAt(0)+o[3].charAt(0):o.join("")}function Dt(t){return Math.round(parseFloat(t)*255).toString(16)}function lt(t){return p(t)/255}function p(t){return parseInt(t,16)}function Ut(t){return{r:t>>16,g:(t&65280)>>8,b:t&255}}var D={aliceblue:"#f0f8ff",antiquewhite:"#faebd7",aqua:"#00ffff",aquamarine:"#7fffd4",azure:"#f0ffff",beige:"#f5f5dc",bisque:"#ffe4c4",black:"#000000",blanchedalmond:"#ffebcd",blue:"#0000ff",blueviolet:"#8a2be2",brown:"#a52a2a",burlywood:"#deb887",cadetblue:"#5f9ea0",chartreuse:"#7fff00",chocolate:"#d2691e",coral:"#ff7f50",cornflowerblue:"#6495ed",cornsilk:"#fff8dc",crimson:"#dc143c",cyan:"#00ffff",darkblue:"#00008b",darkcyan:"#008b8b",darkgoldenrod:"#b8860b",darkgray:"#a9a9a9",darkgreen:"#006400",darkgrey:"#a9a9a9",darkkhaki:"#bdb76b",darkmagenta:"#8b008b",darkolivegreen:"#556b2f",darkorange:"#ff8c00",darkorchid:"#9932cc",darkred:"#8b0000",darksalmon:"#e9967a",darkseagreen:"#8fbc8f",darkslateblue:"#483d8b",darkslategray:"#2f4f4f",darkslategrey:"#2f4f4f",darkturquoise:"#00ced1",darkviolet:"#9400d3",deeppink:"#ff1493",deepskyblue:"#00bfff",dimgray:"#696969",dimgrey:"#696969",dodgerblue:"#1e90ff",firebrick:"#b22222",floralwhite:"#fffaf0",forestgreen:"#228b22",fuchsia:"#ff00ff",gainsboro:"#dcdcdc",ghostwhite:"#f8f8ff",goldenrod:"#daa520",gold:"#ffd700",gray:"#808080",green:"#008000",greenyellow:"#adff2f",grey:"#808080",honeydew:"#f0fff0",hotpink:"#ff69b4",indianred:"#cd5c5c",indigo:"#4b0082",ivory:"#fffff0",khaki:"#f0e68c",lavenderblush:"#fff0f5",lavender:"#e6e6fa",lawngreen:"#7cfc00",lemonchiffon:"#fffacd",lightblue:"#add8e6",lightcoral:"#f08080",lightcyan:"#e0ffff",lightgoldenrodyellow:"#fafad2",lightgray:"#d3d3d3",lightgreen:"#90ee90",lightgrey:"#d3d3d3",lightpink:"#ffb6c1",lightsalmon:"#ffa07a",lightseagreen:"#20b2aa",lightskyblue:"#87cefa",lightslategray:"#778899",lightslategrey:"#778899",lightsteelblue:"#b0c4de",lightyellow:"#ffffe0",lime:"#00ff00",limegreen:"#32cd32",linen:"#faf0e6",magenta:"#ff00ff",maroon:"#800000",mediumaquamarine:"#66cdaa",mediumblue:"#0000cd",mediumorchid:"#ba55d3",mediumpurple:"#9370db",mediumseagreen:"#3cb371",mediumslateblue:"#7b68ee",mediumspringgreen:"#00fa9a",mediumturquoise:"#48d1cc",mediumvioletred:"#c71585",midnightblue:"#191970",mintcream:"#f5fffa",mistyrose:"#ffe4e1",moccasin:"#ffe4b5",navajowhite:"#ffdead",navy:"#000080",oldlace:"#fdf5e6",olive:"#808000",olivedrab:"#6b8e23",orange:"#ffa500",orangered:"#ff4500",orchid:"#da70d6",palegoldenrod:"#eee8aa",palegreen:"#98fb98",paleturquoise:"#afeeee",palevioletred:"#db7093",papayawhip:"#ffefd5",peachpuff:"#ffdab9",peru:"#cd853f",pink:"#ffc0cb",plum:"#dda0dd",powderblue:"#b0e0e6",purple:"#800080",rebeccapurple:"#663399",red:"#ff0000",rosybrown:"#bc8f8f",royalblue:"#4169e1",saddlebrown:"#8b4513",salmon:"#fa8072",sandybrown:"#f4a460",seagreen:"#2e8b57",seashell:"#fff5ee",sienna:"#a0522d",silver:"#c0c0c0",skyblue:"#87ceeb",slateblue:"#6a5acd",slategray:"#708090",slategrey:"#708090",snow:"#fffafa",springgreen:"#00ff7f",steelblue:"#4682b4",tan:"#d2b48c",teal:"#008080",thistle:"#d8bfd8",tomato:"#ff6347",turquoise:"#40e0d0",violet:"#ee82ee",wheat:"#f5deb3",white:"#ffffff",whitesmoke:"#f5f5f5",yellow:"#ffff00",yellowgreen:"#9acd32"};function zt(t){var e={r:0,g:0,b:0},n=1,r=null,a=null,o=null,s=!1,c=!1;return typeof t=="string"&&(t=Jt(t)),typeof t=="object"&&(b(t.r)&&b(t.g)&&b(t.b)?(e=Wt(t.r,t.g,t.b),s=!0,c=String(t.r).substr(-1)==="%"?"prgb":"rgb"):b(t.h)&&b(t.s)&&b(t.v)?(r=N(t.s),a=N(t.v),e=$t(t.h,r,a),s=!0,c="hsv"):b(t.h)&&b(t.s)&&b(t.l)&&(r=N(t.s),o=N(t.l),e=jt(t.h,r,o),s=!0,c="hsl"),Object.prototype.hasOwnProperty.call(t,"a")&&(n=t.a)),n=ut(n),{ok:s,format:t.format||c,r:Math.min(255,Math.max(e.r,0)),g:Math.min(255,Math.max(e.g,0)),b:Math.min(255,Math.max(e.b,0)),a:n}}var Vt="[-\\+]?\\d+%?",Gt="[-\\+]?\\d*\\.\\d+%?",v="(?:".concat(Gt,")|(?:").concat(Vt,")"),U="[\\s|\\(]+(".concat(v,")[,|\\s]+(").concat(v,")[,|\\s]+(").concat(v,")\\s*\\)?"),z="[\\s|\\(]+(".concat(v,")[,|\\s]+(").concat(v,")[,|\\s]+(").concat(v,")[,|\\s]+(").concat(v,")\\s*\\)?"),g={CSS_UNIT:new RegExp(v),rgb:new RegExp("rgb"+U),rgba:new RegExp("rgba"+z),hsl:new RegExp("hsl"+U),hsla:new RegExp("hsla"+z),hsv:new RegExp("hsv"+U),hsva:new RegExp("hsva"+z),hex3:/^#?([0-9a-fA-F]{1})([0-9a-fA-F]{1})([0-9a-fA-F]{1})$/,hex6:/^#?([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/,hex4:/^#?([0-9a-fA-F]{1})([0-9a-fA-F]{1})([0-9a-fA-F]{1})([0-9a-fA-F]{1})$/,hex8:/^#?([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/};function Jt(t){if(t=t.trim().toLowerCase(),t.length===0)return!1;var e=!1;if(D[t])t=D[t],e=!0;else if(t==="transparent")return{r:0,g:0,b:0,a:0,format:"name"};var n=g.rgb.exec(t);return n?{r:n[1],g:n[2],b:n[3]}:(n=g.rgba.exec(t),n?{r:n[1],g:n[2],b:n[3],a:n[4]}:(n=g.hsl.exec(t),n?{h:n[1],s:n[2],l:n[3]}:(n=g.hsla.exec(t),n?{h:n[1],s:n[2],l:n[3],a:n[4]}:(n=g.hsv.exec(t),n?{h:n[1],s:n[2],v:n[3]}:(n=g.hsva.exec(t),n?{h:n[1],s:n[2],v:n[3],a:n[4]}:(n=g.hex8.exec(t),n?{r:p(n[1]),g:p(n[2]),b:p(n[3]),a:lt(n[4]),format:e?"name":"hex8"}:(n=g.hex6.exec(t),n?{r:p(n[1]),g:p(n[2]),b:p(n[3]),format:e?"name":"hex"}:(n=g.hex4.exec(t),n?{r:p(n[1]+n[1]),g:p(n[2]+n[2]),b:p(n[3]+n[3]),a:lt(n[4]+n[4]),format:e?"name":"hex8"}:(n=g.hex3.exec(t),n?{r:p(n[1]+n[1]),g:p(n[2]+n[2]),b:p(n[3]+n[3]),format:e?"name":"hex"}:!1)))))))))}function b(t){return!!g.CSS_UNIT.exec(String(t))}Mt=function(){function t(e,n){e===void 0&&(e=""),n===void 0&&(n={});var r;if(e instanceof t)return e;typeof e=="number"&&(e=Ut(e)),this.originalInput=e;var a=zt(e);this.originalInput=e,this.r=a.r,this.g=a.g,this.b=a.b,this.a=a.a,this.roundA=Math.round(100*this.a)/100,this.format=(r=n.format)!==null&&r!==void 0?r:a.format,this.gradientType=n.gradientType,this.r<1&&(this.r=Math.round(this.r)),this.g<1&&(this.g=Math.round(this.g)),this.b<1&&(this.b=Math.round(this.b)),this.isValid=a.ok}return t.prototype.isDark=function(){return this.getBrightness()<128},t.prototype.isLight=function(){return!this.isDark()},t.prototype.getBrightness=function(){var e=this.toRgb();return(e.r*299+e.g*587+e.b*114)/1e3},t.prototype.getLuminance=function(){var e=this.toRgb(),n,r,a,o=e.r/255,s=e.g/255,c=e.b/255;return o<=.03928?n=o/12.92:n=Math.pow((o+.055)/1.055,2.4),s<=.03928?r=s/12.92:r=Math.pow((s+.055)/1.055,2.4),c<=.03928?a=c/12.92:a=Math.pow((c+.055)/1.055,2.4),.2126*n+.7152*r+.0722*a},t.prototype.getAlpha=function(){return this.a},t.prototype.setAlpha=function(e){return this.a=ut(e),this.roundA=Math.round(100*this.a)/100,this},t.prototype.isMonochrome=function(){var e=this.toHsl().s;return e===0},t.prototype.toHsv=function(){var e=ht(this.r,this.g,this.b);return{h:e.h*360,s:e.s,v:e.v,a:this.a}},t.prototype.toHsvString=function(){var e=ht(this.r,this.g,this.b),n=Math.round(e.h*360),r=Math.round(e.s*100),a=Math.round(e.v*100);return this.a===1?"hsv(".concat(n,", ").concat(r,"%, ").concat(a,"%)"):"hsva(".concat(n,", ").concat(r,"%, ").concat(a,"%, ").concat(this.roundA,")")},t.prototype.toHsl=function(){var e=ft(this.r,this.g,this.b);return{h:e.h*360,s:e.s,l:e.l,a:this.a}},t.prototype.toHslString=function(){var e=ft(this.r,this.g,this.b),n=Math.round(e.h*360),r=Math.round(e.s*100),a=Math.round(e.l*100);return this.a===1?"hsl(".concat(n,", ").concat(r,"%, ").concat(a,"%)"):"hsla(".concat(n,", ").concat(r,"%, ").concat(a,"%, ").concat(this.roundA,")")},t.prototype.toHex=function(e){return e===void 0&&(e=!1),dt(this.r,this.g,this.b,e)},t.prototype.toHexString=function(e){return e===void 0&&(e=!1),"#"+this.toHex(e)},t.prototype.toHex8=function(e){return e===void 0&&(e=!1),Bt(this.r,this.g,this.b,this.a,e)},t.prototype.toHex8String=function(e){return e===void 0&&(e=!1),"#"+this.toHex8(e)},t.prototype.toHexShortString=function(e){return e===void 0&&(e=!1),this.a===1?this.toHexString(e):this.toHex8String(e)},t.prototype.toRgb=function(){return{r:Math.round(this.r),g:Math.round(this.g),b:Math.round(this.b),a:this.a}},t.prototype.toRgbString=function(){var e=Math.round(this.r),n=Math.round(this.g),r=Math.round(this.b);return this.a===1?"rgb(".concat(e,", ").concat(n,", ").concat(r,")"):"rgba(".concat(e,", ").concat(n,", ").concat(r,", ").concat(this.roundA,")")},t.prototype.toPercentageRgb=function(){var e=function(n){return"".concat(Math.round(f(n,255)*100),"%")};return{r:e(this.r),g:e(this.g),b:e(this.b),a:this.a}},t.prototype.toPercentageRgbString=function(){var e=function(n){return Math.round(f(n,255)*100)};return this.a===1?"rgb(".concat(e(this.r),"%, ").concat(e(this.g),"%, ").concat(e(this.b),"%)"):"rgba(".concat(e(this.r),"%, ").concat(e(this.g),"%, ").concat(e(this.b),"%, ").concat(this.roundA,")")},t.prototype.toName=function(){if(this.a===0)return"transparent";if(this.a<1)return!1;for(var e="#"+dt(this.r,this.g,this.b,!1),n=0,r=Object.entries(D);n<r.length;n++){var a=r[n],o=a[0],s=a[1];if(e===s)return o}return!1},t.prototype.toString=function(e){var n=!!e;e=e??this.format;var r=!1,a=this.a<1&&this.a>=0,o=!n&&a&&(e.startsWith("hex")||e==="name");return o?e==="name"&&this.a===0?this.toName():this.toRgbString():(e==="rgb"&&(r=this.toRgbString()),e==="prgb"&&(r=this.toPercentageRgbString()),(e==="hex"||e==="hex6")&&(r=this.toHexString()),e==="hex3"&&(r=this.toHexString(!0)),e==="hex4"&&(r=this.toHex8String(!0)),e==="hex8"&&(r=this.toHex8String()),e==="name"&&(r=this.toName()),e==="hsl"&&(r=this.toHslString()),e==="hsv"&&(r=this.toHsvString()),r||this.toHexString())},t.prototype.toNumber=function(){return(Math.round(this.r)<<16)+(Math.round(this.g)<<8)+Math.round(this.b)},t.prototype.clone=function(){return new t(this.toString())},t.prototype.lighten=function(e){e===void 0&&(e=10);var n=this.toHsl();return n.l+=e/100,n.l=T(n.l),new t(n)},t.prototype.brighten=function(e){e===void 0&&(e=10);var n=this.toRgb();return n.r=Math.max(0,Math.min(255,n.r-Math.round(255*-(e/100)))),n.g=Math.max(0,Math.min(255,n.g-Math.round(255*-(e/100)))),n.b=Math.max(0,Math.min(255,n.b-Math.round(255*-(e/100)))),new t(n)},t.prototype.darken=function(e){e===void 0&&(e=10);var n=this.toHsl();return n.l-=e/100,n.l=T(n.l),new t(n)},t.prototype.tint=function(e){return e===void 0&&(e=10),this.mix("white",e)},t.prototype.shade=function(e){return e===void 0&&(e=10),this.mix("black",e)},t.prototype.desaturate=function(e){e===void 0&&(e=10);var n=this.toHsl();return n.s-=e/100,n.s=T(n.s),new t(n)},t.prototype.saturate=function(e){e===void 0&&(e=10);var n=this.toHsl();return n.s+=e/100,n.s=T(n.s),new t(n)},t.prototype.greyscale=function(){return this.desaturate(100)},t.prototype.spin=function(e){var n=this.toHsl(),r=(n.h+e)%360;return n.h=r<0?360+r:r,new t(n)},t.prototype.mix=function(e,n){n===void 0&&(n=50);var r=this.toRgb(),a=new t(e).toRgb(),o=n/100,s={r:(a.r-r.r)*o+r.r,g:(a.g-r.g)*o+r.g,b:(a.b-r.b)*o+r.b,a:(a.a-r.a)*o+r.a};return new t(s)},t.prototype.analogous=function(e,n){e===void 0&&(e=6),n===void 0&&(n=30);var r=this.toHsl(),a=360/n,o=[this];for(r.h=(r.h-(a*e>>1)+720)%360;--e;)r.h=(r.h+a)%360,o.push(new t(r));return o},t.prototype.complement=function(){var e=this.toHsl();return e.h=(e.h+180)%360,new t(e)},t.prototype.monochromatic=function(e){e===void 0&&(e=6);for(var n=this.toHsv(),r=n.h,a=n.s,o=n.v,s=[],c=1/e;e--;)s.push(new t({h:r,s:a,v:o})),o=(o+c)%1;return s},t.prototype.splitcomplement=function(){var e=this.toHsl(),n=e.h;return[this,new t({h:(n+72)%360,s:e.s,l:e.l}),new t({h:(n+216)%360,s:e.s,l:e.l})]},t.prototype.onBackground=function(e){var n=this.toRgb(),r=new t(e).toRgb(),a=n.a+r.a*(1-n.a);return new t({r:(n.r*n.a+r.r*r.a*(1-n.a))/a,g:(n.g*n.a+r.g*r.a*(1-n.a))/a,b:(n.b*n.a+r.b*r.a*(1-n.a))/a,a})},t.prototype.triad=function(){return this.polyad(3)},t.prototype.tetrad=function(){return this.polyad(4)},t.prototype.polyad=function(e){for(var n=this.toHsl(),r=n.h,a=[this],o=360/e,s=1;s<e;s++)a.push(new t({h:(r+s*o)%360,s:n.s,l:n.l}));return a},t.prototype.equals=function(e){return this.toRgbString()===new t(e).toRgbString()},t}()});export{xt as E,Y as F,Mt as T,ae as __tla,kt as a,Et as d,St as f,tt as i,At as t,Rt as u};
