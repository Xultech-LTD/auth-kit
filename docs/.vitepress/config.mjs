import { defineConfig } from 'vitepress'

export default defineConfig({
    title: 'AuthKit',
    description: 'Reusable Laravel authentication kit',
    base: '/auth-kit/',

    themeConfig: {
        nav: [
            { text: 'Home', link: '/' },
            { text: 'Installation', link: '/installation' },
            { text: 'Architecture', link: '/architecture/overview' },
            { text: 'GitHub', link: 'https://github.com/Xultech-LTD/auth-kit' }
        ],

        sidebar: [
            {
                text: 'Getting Started',
                items: [
                    { text: 'Introduction', link: '/' },
                    { text: 'Installation', link: '/installation' },
                    { text: 'Quick Start', link: '/quick-start' },
                    { text: 'Configuration', link: '/configuration' },
                    { text: 'Upgrade Guide', link: '/upgrade-guide' }
                ]
            },
            {
                text: 'Authentication Flows',
                items: [
                    { text: 'Register', link: '/auth/register' },
                    { text: 'Login', link: '/auth/login' },
                    { text: 'Logout', link: '/auth/logout' },
                    { text: 'Password Confirmation', link: '/auth/password-confirmation' }
                ]
            },
            {
                text: 'Security Flows',
                items: [
                    { text: 'Email Verification', link: '/security/email-verification' },
                    { text: 'Two-Factor Authentication', link: '/security/two-factor' },
                    { text: 'Password Reset', link: '/security/password-reset' },
                    { text: 'Rate Limiting', link: '/security/rate-limiting' },
                    { text: 'Two-Factor Confirmation', link: '/security/confirm-two-factor' }
                ]
            },
            {
                text: 'UI and Frontend',
                items: [
                    { text: 'Blade Views', link: '/ui/blade-views' },
                    { text: 'CSS and Themes', link: '/ui/css-and-themes' }
                ]
            }
        ],

        socialLinks: [
            { icon: 'github', link: 'https://github.com/Xultech-LTD/auth-kit' }
        ]
    }
})