import { defineConfig } from 'vitepress';

export default defineConfig({
    title: 'Laravel Payments',
    description: 'A production-grade, gateway-agnostic payment abstraction for Laravel',

    head: [
        ['meta', { name: 'theme-color', content: '#6366f1' }],
        ['meta', { name: 'og:type', content: 'website' }],
        ['meta', { name: 'og:title', content: 'Laravel Payments' }],
        ['meta', { name: 'og:description', content: 'One canonical payload for every gateway—forever.' }],
    ],

    themeConfig: {
        logo: '/logo.svg',

        nav: [
            { text: 'Guide', link: '/guide/introduction' },
            { text: 'Reference', link: '/reference/configuration' },
            { text: 'API', link: '/api/contracts' },
            {
                text: 'v1.x',
                items: [
                    { text: 'Changelog', link: '/changelog' },
                    { text: 'Upgrade Guide', link: '/upgrade' },
                ],
            },
        ],

        sidebar: {
            '/guide/': [
                {
                    text: 'Getting Started',
                    items: [
                        { text: 'Introduction', link: '/guide/introduction' },
                        { text: 'Installation', link: '/guide/installation' },
                        { text: 'Quick Start', link: '/guide/quick-start' },
                    ],
                },
                {
                    text: 'Core Concepts',
                    items: [
                        { text: 'Canonical Payload', link: '/guide/canonical-payload' },
                        { text: 'Gateway Drivers', link: '/guide/gateway-drivers' },
                        { text: 'Capabilities', link: '/guide/capabilities' },
                        { text: 'Credentials', link: '/guide/credentials' },
                    ],
                },
                {
                    text: 'Usage',
                    items: [
                        { text: 'Creating Payments', link: '/guide/creating-payments' },
                        { text: 'Verifying Payments', link: '/guide/verifying-payments' },
                        { text: 'Refunds', link: '/guide/refunds' },
                        { text: 'Subscriptions', link: '/guide/subscriptions' },
                        { text: 'Webhooks', link: '/guide/webhooks' },
                        { text: 'Events', link: '/guide/events' },
                        { text: 'Logging', link: '/guide/logging' },
                    ],
                },
                {
                    text: 'Extending',
                    items: [
                        { text: 'Creating Gateway Drivers', link: '/guide/creating-drivers' },
                        { text: 'Creating Addon Packages', link: '/guide/creating-addons' },
                        { text: 'Auto-Discovery', link: '/guide/auto-discovery' },
                    ],
                },
            ],

            '/reference/': [
                {
                    text: 'Reference',
                    items: [
                        { text: 'Configuration', link: '/reference/configuration' },
                        { text: 'Database Schema', link: '/reference/database-schema' },
                        { text: 'CLI Commands', link: '/reference/cli-commands' },
                        { text: 'Environment Variables', link: '/reference/environment-variables' },
                    ],
                },
            ],

            '/api/': [
                {
                    text: 'API Reference',
                    items: [
                        { text: 'Contracts', link: '/api/contracts' },
                        { text: 'Data', link: '/api/data' },
                        { text: 'Enums', link: '/api/enums' },
                        { text: 'Events', link: '/api/events' },
                        { text: 'Exceptions', link: '/api/exceptions' },
                        { text: 'Models', link: '/api/models' },
                        { text: 'Facade', link: '/api/facade' },
                    ],
                },
            ],
        },

        socialLinks: [{ icon: 'github', link: 'https://github.com/frolaxhq/laravel-payments' }],

        editLink: {
            pattern: 'https://github.com/frolaxhq/laravel-payments-docs/edit/main/:path',
            text: 'Edit this page on GitHub',
        },

        footer: {
            message: 'Released under the MIT License.',
            copyright: 'Copyright © 2024-present Frolax',
        },

        search: {
            provider: 'local',
        },
    },
});
