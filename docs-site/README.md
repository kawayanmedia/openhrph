# OpenHR PH documentation site

The Docusaurus site for the OpenHR PH documentation. It renders the Markdown in
[`../docs`](../docs) directly, so that directory stays the single source of truth
and the site can never drift from the repository docs.

## Requirements

- Node.js 20 or newer (the CI build uses Node 22)
- npm 10 or newer

## Commands

Run these from `docs-site/`:

```bash
npm ci         # install the locked dependencies
npm start      # development server with hot reload at http://localhost:3000/openhrph/
npm run build  # production build into build/
npm run serve  # serve the production build locally
npm run clear  # clear the Docusaurus cache
```

`npm run build` fails on broken internal links, broken anchors, and MDX errors, so
it is also the documentation check to run before a pull request.

## How the content is wired

| Path | Purpose |
| --- | --- |
| `docusaurus.config.ts` | Site metadata, branding, navbar and footer, docs plugin (`path: '../docs'`), search, mermaid |
| `sidebars.ts` | Curated sidebar: overview, user guide, developer guide, deployment, security and compliance |
| `src/pages/index.tsx` | Landing page with the three reading paths |
| `src/pages/screens.tsx` | Light/dark gallery of the admin workspace screenshots |
| `src/css/custom.css` | Emerald-on-slate theme matching the product UI |
| `static/img/` | Text wordmark, favicon, and social card |

### Adding or renaming a page

1. Add the Markdown file under `docs/`.
2. Add its doc ID (its path without `.md`) to `sidebars.ts`. A page that is not in
   the sidebar still builds, but it will be hard to find.
3. Add frontmatter when the URL should differ from the file path:

   ```markdown
   ---
   slug: /user-guide
   description: A one-line summary used for search results and social cards.
   ---
   ```

Links between docs use paths relative to the file, for example
`../deployment/docker.md`. Links to repository files outside `docs/` must use the
full GitHub URL, because the site only renders the `docs/` tree.

## Deployment

The site is published to GitHub Pages by
[`.github/workflows/docs.yml`](../.github/workflows/docs.yml).

- Pull requests that touch `docs/` or `docs-site/` run the build as a check.
- Pushes to `main` build and deploy to <https://kawayanmedia.github.io/openhrph/>.

One-time repository setup: open **Settings → Pages** and set **Source** to
**GitHub Actions**. GitHub Pages for a private repository requires a plan that
includes it (Pro, Team, or Enterprise); on the Free plan, either publish the site
from a public mirror or host `docs-site/build` on any static host. If you fork the
project, also update `url`, `baseUrl`, `organizationName`, and `projectName` in
`docusaurus.config.ts` to match your repository, or the assets will resolve from the
wrong path.

## Notes

- `baseUrl` is `/openhrph/` for the GitHub Pages project site. A custom domain or a
  user/organisation site needs `baseUrl: '/'`.
- The light and dark screenshots live in `../docs/images` and are imported by
  `src/pages/index.tsx` and `src/pages/screens.tsx`.
- Search is local (`@easyops-cn/docusaurus-search-local`); there are no Algolia
  keys to configure.
