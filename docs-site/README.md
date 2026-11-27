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
npm run guides # regenerate the two user guide editions
```

`npm run build` fails on broken internal links, broken anchors, and MDX errors, so
it is also the documentation check to run before a pull request.

## How the content is wired

| Path | Purpose |
| --- | --- |
| `docusaurus.config.ts` | Site metadata, branding, navbar and footer, the three docs instances (`docs`, `guide-private`, `guide-government`), search, mermaid |
| `sidebars.ts` | Sidebar for the documentation outside the user guide |
| `sidebars/privateGuide.ts`, `sidebars/governmentGuide.ts` | Sidebars for the two user guide editions |
| `scripts/build-sector-guides.mjs` | Generates the two guide editions from `../docs/user-guide` into `.generated/` |
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

### The two user guide editions

The user guide is written once in `docs/user-guide` and published twice: a
Private-sector edition at `/docs/user-guide/private` and a Government-sector edition
at `/docs/user-guide/government`. Readers pick an edition from the **User guide**
menu, and each edition's sidebar contains that sector's pages plus its own copy of
the shared modules.

Docusaurus chains every MDX loader whose directory contains a file, so two docs
instances cannot point at the same directory. `scripts/build-sector-guides.mjs`
therefore writes a Private copy and a Government copy into `docs-site/.generated`
(gitignored) and rewrites every link and image to the position the page has in that
copy. It runs automatically before `npm start` and `npm run build`; run it directly
after editing anything under `docs/user-guide`.

- Author every page in `docs/user-guide/shared`, `docs/user-guide/private`, or
  `docs/user-guide/government`. Never edit `docs-site/.generated`.
- Keep links between user guide pages as normal relative `.md` links. The generator
  resolves them, including links that cross into the other sector edition.
- A page that is not part of the user guide (the documentation hub, the developer
  guide) cannot link to an edition with a relative `.md` link, because it belongs to
  a different docs instance. Use `<Link to="/docs/user-guide/...">` from
  `@docusaurus/Link` in those pages; it keeps the `baseUrl` prefix and is checked by
  `npm run build`.

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
  `src/pages/index.tsx`, `src/pages/screens.tsx`, and the user guide pages.
- `npm run docs:screenshots` from the repository root re-captures the user guide
  screenshots from a running installation. Run it against a workspace that holds
  synthetic data only, and point it elsewhere with `APP_URL`, `E2E_EMAIL`, and
  `E2E_PASSWORD`.
- Search is local (`@easyops-cn/docusaurus-search-local`); there are no Algolia
  keys to configure.
