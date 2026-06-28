import { defineConfig } from 'vitepress'

export default defineConfig({
  srcDir: 'docs',
  base: '/DTOkit/',
  title: 'DTOKit',
  description: 'Framework-agnostic typed data boundaries for PHP',
  cleanUrls: true,
  lastUpdated: true,

  themeConfig: {
    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Getting Started', link: '/getting-started' },
          { text: 'Core Concepts', link: '/concepts' },
        ],
      },
      {
        text: 'Guide',
        items: [
          { text: 'Mapping Input', link: '/mapping' },
          { text: 'Serialization', link: '/serialization' },
          { text: 'Attributes', link: '/attributes' },
          { text: 'Errors and Explain Mode', link: '/diagnostics' },
          { text: 'Custom Casts and Transformers', link: '/extensions' },
          { text: 'Testing Data Boundaries', link: '/testing' },
          { text: 'Security', link: '/security' },
          { text: 'Recipes', link: '/recipes' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'API Reference', link: '/api' },
          { text: 'Supported Types and Limits', link: '/limitations' },
          { text: 'Release Guide', link: '/releasing' },
        ],
      },
    ],
    search: { provider: 'local' },
    outline: { level: [2, 3], label: 'On this page' },
    docFooter: { prev: 'Previous', next: 'Next' },
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 DTOKit contributors',
    },
  },
})
