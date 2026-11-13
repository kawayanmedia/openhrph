import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  docs: [
    {
      type: 'doc',
      id: 'README',
      label: 'Documentation hub',
    },
    {
      type: 'category',
      label: 'User guide',
      link: {type: 'doc', id: 'user-guide/README'},
      collapsed: false,
      items: [
        {
          type: 'doc',
          id: 'user-guide/end-to-end-walkthrough',
          label: 'Company to payslip walkthrough',
        },
        'user-guide/getting-started',
        'user-guide/employees',
        'user-guide/attendance-and-requests',
        'user-guide/payroll',
        'user-guide/reports-and-payslips',
        'user-guide/settings-and-access',
        'user-guide/troubleshooting',
      ],
    },
    {
      type: 'category',
      label: 'Developer guide',
      link: {type: 'doc', id: 'developer-guide/README'},
      collapsed: false,
      items: [
        'developer-guide/architecture',
        'developer-guide/local-development',
        'developer-guide/admin-panel',
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
        'deployment/operations',
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
