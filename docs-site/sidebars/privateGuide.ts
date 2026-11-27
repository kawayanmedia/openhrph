import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

/**
 * Private-sector user guide. Contains every Private-only page plus the shared
 * modules, so a reader who picks this guide never sees Government pages.
 */
const sidebars: SidebarsConfig = {
  privateGuide: [
    {
      type: 'category',
      label: 'Start here',
      collapsed: false,
      items: [
        'shared/end-to-end-walkthrough',
        'shared/getting-started',
        'shared/data-import',
      ],
    },
    {
      type: 'category',
      label: 'Private sector',
      link: {type: 'doc', id: 'private-hr'},
      collapsed: false,
      items: [
        'private-recruitment',
        'private-employment',
        'private-separation',
      ],
    },
    {
      type: 'category',
      label: 'Shared modules',
      collapsed: false,
      items: [
        {
          type: 'category',
          label: 'People and organization',
          collapsed: false,
          items: [
            'shared/employees',
            'shared/organization-and-positions',
            'shared/documents-and-workflow',
          ],
        },
        {
          type: 'category',
          label: 'Time and attendance',
          collapsed: false,
          items: [
            'shared/attendance-and-requests',
            'shared/timesheet-management',
            'shared/workforce-corrections',
          ],
        },
        {
          type: 'category',
          label: 'Payroll and reporting',
          collapsed: false,
          items: [
            'shared/payroll',
            'shared/reports-and-payslips',
            'shared/workforce-analytics',
          ],
        },
        {
          type: 'category',
          label: 'HR administration',
          collapsed: false,
          items: [
            'shared/hr-and-workforce',
            'shared/performance',
            'shared/learning',
            'shared/retirement',
            'shared/compliance',
            'shared/employee-self-service',
          ],
        },
        {
          type: 'category',
          label: 'Settings and help',
          collapsed: false,
          items: [
            'shared/settings-and-access',
            'shared/troubleshooting',
          ],
        },
      ],
    },
  ],
};

export default sidebars;
