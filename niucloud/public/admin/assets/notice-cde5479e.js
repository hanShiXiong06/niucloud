import{x as e}from"./index-d5447f06.js";function n(){return e.get("notice/notice")}function o(t){return e.post("notice/notice/editstatus",t,{showSuccessMessage:!0})}function i(t){return e.post("notice/notice/edit",t,{showSuccessMessage:!0})}function c(){return e.get("notice/notice/sms")}function u(t){return e.get(`notice/notice/sms/${t}`)}function r(t){return e.put(`notice/notice/sms/${t.sms_type}`,t,{showSuccessMessage:!0})}function g(t){return e.get("notice/sms/log",t)}export{i as a,r as b,n as c,c as d,o as e,g as f,u as g};
