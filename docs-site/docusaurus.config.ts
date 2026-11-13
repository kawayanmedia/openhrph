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
        docs: {
          path: '../docs',
          routeBasePath: 'docs',
          sidebarPath: './sidebars.ts',
          editUrl: `${repository}/edit/main/docs/`,
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
    [
      '@easyops-cn/docusaurus-search-local',
      {
        hashed: true,
        language: ['en'],
        docsRouteBasePath: 'docs',
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
        {type: 'doc', docId: 'user-guide/README', label: 'User guide', position: 'left'},
        {type: 'doc', docId: 'developer-guide/README', label: 'Developer guide', position: 'left'},
        {type: 'doc', docId: 'deployment/README', label: 'Deployment', position: 'left'},
        {to: '/screens', label: 'Screens', position: 'left'},
        {href: repository, label: 'GitHub', position: 'right'},
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Use OpenHR PH',
          items: [
            {label: 'Company to payslip walkthrough', to: '/docs/user-guide/end-to-end-walkthrough'},
            {label: 'Getting started', to: '/docs/user-guide/getting-started'},
            {label: 'Payroll', to: '/docs/user-guide/payroll'},
            {label: 'Reports and payslips', to: '/docs/user-guide/reports-and-payslips'},
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
