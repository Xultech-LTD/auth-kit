import { defineConfig } from 'vitepress'

export default defineConfig({
    title: 'AuthKit',
    description: 'Reusable Laravel authentication kit',
    base: '/auth-kit/',

    themeConfig: {
        nav: [
            { text: 'Home', link: '/' },
            { text: 'Installation', link: '/installation' },
            { text: 'GitHub', link: 'https://github.com/Xultech-LTD/auth-kit' }
        ],

        sidebar: [
            {
                text: 'Getting Started',
                items: [
                    { text: 'Installation', link: '/installation' },
                    { text: 'Configuration', link: '/configuration' }
                ]
            },
            {
                text: 'Authentication',
                items: [
                    { text: 'Register', link: '/register' },
                    { text: 'Login', link: '/login' }
                ]
            },
            {
                text: 'Email Verification',
                items: [
                    { text: 'Overview', link: '/email-verification' }
                ]
            },
            {
                text: 'Two-Factor Authentication',
                items: [
                    { text: 'Overview', link: '/two-factor' }
                ]
            },
            {
                text: 'Password Reset',
                items: [
                    { text: 'Overview', link: '/password-reset' }
                ]
            },
            {
                text: 'Security',
                items: [
                    { text: 'Rate Limiting', link: '/rate-limiting' }
                ]
            },
            {
                text: 'Extending',
                items: [
                    { text: 'Extending AuthKit', link: '/extending-authkit' }
                ]
            }
        ],

        socialLinks: [
            { icon: 'github', link: 'https://github.com/Xultech-LTD/auth-kit' }
        ]
    }
})