import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const repository = 'https://github.com/kawayanmedia/openhrph';

const config: Config = {
  title: 'OpenHR PH',
  tagline: 'OpenHR PH documentation: open-source HR and Philippine payroll',
  favicon: 'img/favicon.svg',

  url: 'https://kawayanmedia.github.io',
  baseUrl: '/openhrph/',
  organizationName: 'kawayanmedia',
  projectName: 'openhrph',
  trailingSlash: false,

  onBrokenLinks: 'throw',

  markdown: {
    mermaid: true,
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  themes: ['@docusaurus/theme-mermaid'],

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      {
        // The repository's docs/ directory is the single source of truth.
        // The user guide body is rendered by the two sector instances below.
        docs: {
          path: '../docs',
          routeBasePath: 'docs',
          sidebarPath: './sidebars.ts',
          editUrl: `${repository}/edit/main/docs/`,
          exclude: ['user-guide/shared/**', 'user-guide/private/**', 'user-guide/government/**'],
          showLastUpdateTime: true,
          breadcrumbs: true,
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  plugins: [
    // The user guide is published twice from one source tree: a Private-sector
    // guide and a Government-sector guide. Each instance owns its sector pages
    // plus its own rendering of the shared modules, so a reader who picks a
    // sector never sees the other sector's pages in the sidebar.
    [
      '@docusaurus/plugin-content-docs',
      {
        id: 'guide-private',
        path: './.generated/user-guide/private',
        routeBasePath: 'docs/user-guide/private',
        sidebarPath: './sidebars/privateGuide.ts',
        editUrl: ({docPath}) => `${repository}/edit/main/docs/user-guide/${docPath.startsWith('shared/') ? docPath : `private/${docPath}`}`,
        breadcrumbs: true,
      },
    ],
    [
      '@docusaurus/plugin-content-docs',
      {
        id: 'guide-government',
        path: './.generated/user-guide/government',
        routeBasePath: 'docs/user-guide/government',
        sidebarPath: './sidebars/governmentGuide.ts',
        editUrl: ({docPath}) => `${repository}/edit/main/docs/user-guide/${docPath.startsWith('shared/') ? docPath : `government/${docPath}`}`,
        breadcrumbs: true,
      },
    ],
    [
      '@easyops-cn/docusaurus-search-local',
      {
        hashed: true,
        language: ['en'],
        docsRouteBasePath: ['docs', 'docs/user-guide/private', 'docs/user-guide/government'],
        docsDir: '../docs',
        indexBlog: false,
        highlightSearchTermsOnTargetPage: true,
        searchResultLimits: 8,
      },
    ],
  ],

  themeConfig: {
    image: 'img/social-card.svg',
    navbar: {
      logo: {
        alt: 'OpenHR PH',
        src: 'img/logo.svg',
        srcDark: 'img/logo-dark.svg',
      },
      items: [
        {type: 'doc', docId: 'README', label: 'Documentation', position: 'left'},
        {
          type: 'dropdown',
          label: 'User guide',
          position: 'left',
          items: [
            {type: 'doc', docId: 'user-guide/README', label: 'Choose your sector'},
            {
              type: 'doc',
              docId: 'private-hr',
              docsPluginId: 'guide-private',
              label: 'Private sector guide',
            },
            {
              type: 'doc',
              docId: 'government-hr',
              docsPluginId: 'guide-government',
              label: 'Government sector guide',
            },
          ],
        },
        {type: 'doc', docId: 'developer-guide/README', label: 'Developer guide', position: 'left'},
        {type: 'doc', docId: 'deployment/README', label: 'Deployment', position: 'left'},
        {to: '/screens', label: 'Screens', position: 'left'},
        {href: 'https://openhrph.laravel.cloud', label: 'Live demo', position: 'right'},
        {href: repository, label: 'GitHub', position: 'right'},
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Use OpenHR PH',
          items: [
            {label: 'Live demo', href: 'https://openhrph.laravel.cloud'},
            {label: 'Choose your user guide', to: '/docs/user-guide'},
            {label: 'Private sector guide', to: '/docs/user-guide/private/private-hr'},
            {label: 'Government sector guide', to: '/docs/user-guide/government/government-hr'},
            {label: 'Payroll', to: '/docs/user-guide/private/shared/payroll'},
          ],
        },
        {
          title: 'Build and operate',
          items: [
            {label: 'Architecture', to: '/docs/developer-guide/architecture'},
            {label: 'Local development', to: '/docs/developer-guide/local-development'},
            {label: 'Admin panel', to: '/docs/developer-guide/admin-panel'},
            {label: 'Testing', to: '/docs/developer-guide/testing'},
          ],
        },
        {
          title: 'Deploy',
          items: [
            {label: 'Deployment overview', to: '/docs/deployment'},
            {label: 'Docker Compose', to: '/docs/deployment/docker'},
            {label: 'Operations', to: '/docs/deployment/operations'},
            {label: 'Laravel Cloud', to: '/docs/deployment/laravel-cloud'},
            {label: 'Serverless / pause when idle', to: '/docs/deployment/serverless'},
            {label: 'Desktop app', to: '/docs/deployment/desktop'},
          ],
        },
        {
          title: 'Project',
          items: [
            {label: 'GitHub', href: repository},
            {label: 'Contributing', href: `${repository}/blob/main/CONTRIBUTING.md`},
            {label: 'Security policy', href: `${repository}/blob/main/SECURITY.md`},
            {label: 'Compliance audit', to: '/docs/payroll-audit/2026-11-payroll-compliance-audit'},
          ],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} OpenHR PH contributors. Built with Docusaurus.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash', 'json', 'yaml', 'ini', 'sql', 'go', 'diff', 'docker', 'nginx'],
    },
    mermaid: {
      theme: {light: 'neutral', dark: 'dark'},
    },
    colorMode: {
      defaultMode: 'light',
      disableSwitch: false,
      respectPrefersColorScheme: true,
    },
    tableOfContents: {
      minHeadingLevel: 2,
      maxHeadingLevel: 3,
    },
    docs: {
      sidebar: {
        hideable: true,
        autoCollapseCategories: false,
      },
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
