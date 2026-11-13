import type {ReactNode} from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useBaseUrl from '@docusaurus/useBaseUrl';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';
import ThemedImage from '@theme/ThemedImage';
import styles from './index.module.css';

import settingsLight from '../../../docs/images/admin-settings-light.png';
import settingsDark from '../../../docs/images/admin-settings-dark.png';

type Pathway = {
  title: string;
  description: string;
  bullets: string[];
  to: string;
  action: string;
};

const pathways: Pathway[] = [
  {
    title: 'I use OpenHR PH',
    description: 'Day-to-day work for HR officers, payroll staff, administrators, and employees.',
    bullets: [
      'Sign in, switch companies, and change your password',
      'Employee records, attendance, and requests',
      'Payroll runs, review, approval, and payslips',
      'Reports, remittances, settings, and access',
    ],
    to: '/docs/user-guide',
    action: 'Open the user guide',
  },
  {
    title: 'I build or extend OpenHR PH',
    description: 'Architecture, conventions, and extension points for Laravel developers.',
    bullets: [
      'Request lifecycle, tenancy, and the Livewire admin',
      'Adding resources, fields, filters, actions, and metrics',
      'The Go payroll engine and statutory tables',
      'Pest, Playwright, and Go test workflows',
    ],
    to: '/docs/developer-guide',
    action: 'Open the developer guide',
  },
  {
    title: 'I deploy or operate OpenHR PH',
    description: 'Golden images, Docker Compose, managed hosting, and runbooks.',
    bullets: [
      'Docker Compose for a reproducible self-hosted stack',
      'AWS, GCP, and Azure images with Terraform',
      'Laravel Cloud and Vapor considerations',
      'Backups, scaling, health checks, and teardown',
    ],
    to: '/docs/deployment',
    action: 'Open the deployment guide',
  },
];

function PathwayCard({pathway}: {pathway: Pathway}): ReactNode {
  return (
    <Link className={clsx('pathCard', styles.pathCard)} to={pathway.to}>
      <Heading as="h3">{pathway.title}</Heading>
      <p>{pathway.description}</p>
      <ul>
        {pathway.bullets.map((bullet) => (
          <li key={bullet}>{bullet}</li>
        ))}
      </ul>
      <span className="pathCardAction">{pathway.action} →</span>
    </Link>
  );
}

export default function Home(): ReactNode {
  const screensPath = useBaseUrl('/screens');

  return (
    <Layout
      title="Documentation"
      description="OpenHR PH documentation: open-source HR and Philippine payroll, with the user guide, developer guide, deployment, and the 2026 payroll compliance audit.">
      <header className="heroBanner">
        <div className="container">
          <Heading as="h1" className="heroWordmark">
            OpenHR <span>PH</span>
          </Heading>
          <p className="heroTagline">
            Everything you need to run, extend, and deploy OpenHR PH, an open-source HR and
            payroll platform for Philippine statutory compliance. Three reading paths, one
            shared source of truth.
          </p>
          <div className="heroButtons">
            <Link className="button button--primary button--lg" to="/docs/user-guide">
              I use OpenHR PH
            </Link>
            <Link className="button button--secondary button--lg" to="/docs/developer-guide">
              I build or operate OpenHR PH
            </Link>
            <Link className="button button--outline button--lg" to={screensPath}>
              See the workspace
            </Link>
          </div>
          <div className="heroShot">
            <ThemedImage
              alt="The OpenHR PH workspace showing general settings"
              sources={{light: settingsLight, dark: settingsDark}}
            />
          </div>
        </div>
      </header>

      <main>
        <section className="section">
          <div className="container">
            <Heading as="h2" className="sectionTitle">
              Choose your path
            </Heading>
            <p className="sectionLead">
              The documentation is split by what you need to do. Start with the path that
              matches your role, then use the sidebar to move around.
            </p>
            <div className="cardGrid">
              {pathways.map((pathway) => (
                <PathwayCard key={pathway.title} pathway={pathway} />
              ))}
            </div>
          </div>
        </section>

        <section className={clsx('section', 'sectionAlt')}>
          <div className="container">
            <Heading as="h2" className="sectionTitle">
              New to the system?
            </Heading>
            <p className="sectionLead">
              Follow one complete first run from the company profile to a printed payslip.
              It covers the settings, reference data, payroll group, employee, portal access,
              payroll, and the correction cycle.
            </p>
            <div className="heroButtons">
              <Link
                className="button button--primary button--lg"
                to="/docs/user-guide/end-to-end-walkthrough">
                Start the company-to-payslip walkthrough
              </Link>
              <Link
                className="button button--outline button--lg"
                to="/docs/developer-guide/local-development">
                Set up a development environment
              </Link>
            </div>
          </div>
        </section>

        <section className="section">
          <div className="container">
            <div className="cardGrid">
              <div className="pathCard">
                <Heading as="h3">Philippine statutory payroll</Heading>
                <p>
                  Effective-dated SSS, PhilHealth, Pag-IBIG, BIR withholding tax, and
                  minimum-wage tables, with a 2026 compliance audit and open findings.
                </p>
                <Link
                  className="pathCardAction"
                  to="/docs/payroll-audit/2026-11-payroll-compliance-audit">
                  Read the compliance audit →
                </Link>
              </div>
              <div className="pathCard">
                <Heading as="h3">Multi-company by design</Heading>
                <p>
                  A single installation serves several companies. Learn how tenant
                  resolution, query scoping, queues, cache, and storage stay isolated.
                </p>
                <Link className="pathCardAction" to="/docs/tenancy">
                  Read the tenancy guide →
                </Link>
              </div>
              <div className="pathCard">
                <Heading as="h3">Ready to contribute?</Heading>
                <p>
                  Branching, conventions, formatting, tests, and pull request expectations
                  for the OpenHR PH repository.
                </p>
                <Link
                  className="pathCardAction"
                  href="https://github.com/kawayanmedia/openhrph/blob/main/CONTRIBUTING.md">
                  Read the contributing guide →
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
}
