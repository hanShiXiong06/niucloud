import{x as s}from"./index-d5447f06.js";function r(e){return s.get("user",{params:e})}function t(e){return s.get(`user/${e}`)}function n(e){return s.post("user",e,{showSuccessMessage:!0})}function o(e){return s.put(`user/${e.uid}`,e,{showSuccessMessage:!0})}function c(e){return s.put(`user/lock/${e}`,{},{showSuccessMessage:!0})}function g(e){return s.put(`user/unlock/${e}`,{},{showSuccessMessage:!0})}function a(e){return s.get("user/userlog",{params:e})}function i(e){return s.get(`user/userlog/${e}`)}export{n as a,i as b,a as c,r as d,o as e,t as g,c as l,g as u};
