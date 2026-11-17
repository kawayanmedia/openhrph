import type {ReactNode} from 'react';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';
import ThemedImage from '@theme/ThemedImage';

import loginLight from '../../../docs/images/admin-login-light.png';
import formLight from '../../../docs/images/admin-form-light.png';
import formDark from '../../../docs/images/admin-form-dark.png';
import settingsLight from '../../../docs/images/admin-settings-light.png';
import settingsDark from '../../../docs/images/admin-settings-dark.png';

type Screen = {
  title: string;
  description: string;
  light: string;
  dark?: string;
};

const screens: Screen[] = [
  {
    title: 'Sign in',
    description:
      'The workspace sign-in screen at /app/login, with password reveal and theme toggle.',
    light: loginLight,
  },
  {
    title: 'Create Office',
    description:
      'A standard create form: required fields marked with an asterisk, grouped sections, and a sticky action bar.',
    light: formLight,
    dark: formDark,
  },
  {
    title: 'General settings',
    description:
      'Company profile, statutory employer numbers, logo upload, and employee-number automation.',
    light: settingsLight,
    dark: settingsDark,
  },
];

export default function Screens(): ReactNode {
  return (
    <Layout
      title="Workspace screens"
      description="Light and dark screenshots of the OpenHR PH admin workspace.">
      <main className="container margin-vert--lg">
        <Heading as="h1">Workspace screens</Heading>
        <p>
          The admin workspace at <code>/app</code> supports light and dark themes.
          Use the toggle in the navbar to preview either mode; the Create Office and
          General settings screenshots follow your current theme, while the sign-in
          screen below is captured in light mode.
        </p>
        <div className="screenGrid">
          {screens.map((screen) => (
            <figure className="screenCard" key={screen.title}>
              {screen.dark ? (
                <ThemedImage
                  alt={`${screen.title} screen`}
                  sources={{light: screen.light, dark: screen.dark}}
                />
              ) : (
                <img src={screen.light} alt={`${screen.title} screen`} />
              )}
              <figcaption className="screenCardBody">
                <Heading as="h3">{screen.title}</Heading>
                <p>{screen.description}</p>
              </figcaption>
            </figure>
          ))}
        </div>
      </main>
    </Layout>
  );
}
