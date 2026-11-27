import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

/**
 * Sidebar for the documentation that is not part of a sector guide. The user
 * guide is published by the `guide-private` and `guide-government` docs
 * instances, each with its own sidebar in `sidebars/`.
 */
const sidebars: SidebarsConfig = {
  docs: [
    {
      type: 'doc',
      id: 'README',
      label: 'Documentation hub',
    },
    {
      type: 'doc',
      id: 'user-guide/README',
      label: 'User guide: choose your sector',
    },
    {
      type: 'category',
      label: 'Developer guide',
      link: {type: 'doc', id: 'developer-guide/README'},
      collapsed: false,
      items: [
        'developer-guide/architecture',
        'developer-guide/hr-platform',
        'developer-guide/local-development',
        'developer-guide/admin-panel',
        'developer-guide/data-import',
        'developer-guide/payroll-engine',
        'developer-guide/testing',
        'developer-guide/troubleshooting',
        'tenancy',
      ],
    },
    {
      type: 'category',
      label: 'Deployment',
      link: {type: 'doc', id: 'deployment/README'},
      collapsed: false,
      items: [
        'deployment/docker',
        'deployment/aws-ami',
        'deployment/gcp',
        'deployment/azure',
        'deployment/laravel-cloud',
        'deployment/serverless',
        'deployment/desktop',
        'deployment/operations',
      ],
    },
    {
      type: 'category',
      label: 'Release notes',
      collapsed: false,
      items: [
        'releases/2026-11-hr-platform',
      ],
    },
    {
      type: 'category',
      label: 'Security and compliance',
      collapsed: false,
      items: [
        'security/open-source-checklist',
        'payroll-audit/2026-11-payroll-compliance-audit',
      ],
    },
  ],
};

export default sidebars;
