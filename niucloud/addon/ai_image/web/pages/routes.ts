export default [
    // {
    //     path: "/",
    //     component: () => import('~/addon/ai_image/pages/index/index.vue'),
    //     meta: {
    //         layout: "aiimage"
    //     }
    // },

    {
        path: "/ai_image/index",
        component: () => import('~/addon/ai_image/pages/index/index.vue'),
        meta: {
            layout: "aiimage"
        }
    },

    {
        path: "/ai_image/image/image",
        component: () => import('~/addon/ai_image/pages/image/image.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/history/image",
        component: () => import('~/addon/ai_image/pages/history/image.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/package/index",
        component: () => import('~/addon/ai_image/pages/package/index.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/package/order",
        component: () => import('~/addon/ai_image/pages/package/order.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/member/point",
        component: () => import('~/addon/ai_image/pages/member/point.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/member/center",
        component: () => import('~/addon/ai_image/pages/member/center.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/card/card",
        component: () => import('~/addon/ai_image/pages/card/card.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/card/verify",
        component: () => import('~/addon/ai_image/pages/card/verify.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/help/index",
        component: () => import('~/addon/ai_image/pages/help/index.vue'),
        meta: {
            layout: "aiimage",
            middleware: "auth"
        }
    },
    {
        path: "/ai_image/auth/login",
        component: () => import('~/addon/ai_image/pages/auth/login.vue'),
        meta: {
            layout: "ai_image"
        }
    },
    {
        path: "/ai_image/auth/register",
        component: () => import('~/addon/ai_image/pages/auth/register.vue'),
        meta: {
            layout: "ai_image"
        }
    },
    {
        path: "/ai_image/auth/bind",
        component: () => import('~/addon/ai_image/pages/auth/bind.vue'),
        meta: {
            layout: "ai_image"
        }
    },
    {
        path: "/auth/agreement",
        component: () => import('~/addon/ai_image/pages/auth/agreement.vue')
    },
]
