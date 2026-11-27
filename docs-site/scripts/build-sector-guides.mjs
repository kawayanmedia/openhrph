#!/usr/bin/env node
/**
 * Builds the two published user guides from the single source tree in `docs/user-guide`.
 *
 * Docusaurus registers one MDX loader rule per docs instance and chains every rule
 * whose directory contains the file, so two instances cannot share a directory
 * without compiling the same page twice. This script writes a Private copy and a
 * Government copy of the guide into `docs-site/.generated`, and rewrites every
 * internal link and image to the position the page has in that copy.
 *
 * Run it with `npm run guides` (it also runs automatically before `npm start` and
 * `npm run build`). Use `npm run guides -- --check` to verify the generated copy is
 * current without writing anything.
 */
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const siteDir = path.resolve(scriptDir, '..');
const repoRoot = path.resolve(siteDir, '..');
const docsDir = path.join(repoRoot, 'docs');
const guideDir = path.join(docsDir, 'user-guide');
const imagesDir = path.join(docsDir, 'images');
const generatedDir = path.join(siteDir, '.generated');
const generatedImagesDir = path.join(generatedDir, 'images');
const guideRouteBase = '/docs/user-guide';

/** Sector guides, in the order they are published. */
const sectors = ['private', 'government'];

const checkOnly = process.argv.includes('--check');

/**
 * Every page a user guide page may link to, keyed by its absolute source path.
 * Guide pages carry one permalink per sector; other pages carry a single permalink.
 *
 * @typedef {{rel: string, permalink?: string, sectorPermalinks?: Record<string, string>}} DocLink
 * @type {Map<string, DocLink>}
 */
const docs = new Map();

/** @param {string} directory @returns {string[]} */
function markdownFiles(directory) {
  return fs
    .readdirSync(directory, {withFileTypes: true})
    .flatMap((entry) => {
      const entryPath = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        return markdownFiles(entryPath);
      }
      return /\.mdx?$/.test(entry.name) ? [entryPath] : [];
    })
    .sort();
}

/** @param {string} relativePath */
function permalinkFromPath(relativePath) {
  const withoutExtension = relativePath.replace(/\.mdx?$/, '');
  const segments = withoutExtension.split(path.sep);
  if (segments.at(-1) === 'README' || segments.at(-1) === 'index') {
    segments.pop();
  }
  return ['/docs', ...segments].join('/').replace(/\/$/, '');
}

/** @param {string} file */
function declaredSlug(file) {
  const frontMatter = /^---\n([\s\S]*?)\n---/.exec(fs.readFileSync(file, 'utf8'));
  const slug = frontMatter && /^slug:\s*(\S+)$/m.exec(frontMatter[1]);
  return slug ? slug[1] : undefined;
}

/**
 * Pages published by the default docs instance, which keeps the user guide hub.
 */
function registerDocumentationPages() {
  for (const file of markdownFiles(docsDir)) {
    const relative = path.relative(docsDir, file);
    const inSectorFolder =
      relative.startsWith(`user-guide${path.sep}shared${path.sep}`) ||
      relative.startsWith(`user-guide${path.sep}private${path.sep}`) ||
      relative.startsWith(`user-guide${path.sep}government${path.sep}`);

    if (inSectorFolder) {
      continue;
    }

    const slug = declaredSlug(file);
    const permalink = slug === undefined
      ? permalinkFromPath(relative)
      : `/docs${slug === '/' ? '' : slug}`;

    docs.set(file, {rel: relative, permalink});
  }
}

/**
 * Pages of one sector guide. Sector pages are published at the root of the guide
 * and shared pages under `shared/`, which is also where they are generated.
 *
 * @param {string} sector
 * @returns {Map<string, string>} source file to generated relative path
 */
function sectorPages(sector) {
  const pages = new Map();
  for (const file of markdownFiles(path.join(guideDir, 'shared'))) {
    pages.set(file, `shared/${path.basename(file)}`);
  }
  for (const file of markdownFiles(path.join(guideDir, sector))) {
    pages.set(file, path.basename(file));
  }
  return pages;
}

/** Registry of every guide page, with the URL it has in each sector. */
function registerGuidePages() {
  for (const sector of sectors) {
    for (const [file, rel] of sectorPages(sector)) {
      const page = docs.get(file) ?? {rel, sectorPermalinks: {}};
      page.sectorPermalinks = page.sectorPermalinks ?? {};
      page.sectorPermalinks[sector] = `${guideRouteBase}/${sector}/${rel.replace(/\.mdx?$/, '')}`;
      docs.set(file, page);
    }
  }
}

const markdownLink = /(!?)\[([^\]]*)\]\(([^)\s]+)(\s+"[^"]*")?\)/g;

/**
 * Rewrites a single link or image so it resolves from the generated page.
 *
 * @param {{image: boolean, target: string, sourceFile: string, pageUrl: string, outFile: string, sector: string}} link
 */
function rewriteTarget({image, target, sourceFile, pageUrl, outFile, sector}) {
  if (/^(https?:|mailto:|tel:|#|pathname:\/\/|\/)/.test(target)) {
    return target;
  }

  const [rawPath, anchor] = target.split('#');
  if (rawPath === '') {
    return target;
  }

  const resolved = path.resolve(path.dirname(sourceFile), decodeURI(rawPath));
  const suffix = anchor === undefined ? '' : `#${anchor}`;

  if (image || !/\.mdx?$/.test(resolved)) {
    if (!fs.existsSync(resolved) || !resolved.startsWith(imagesDir)) {
      throw new Error(`${path.relative(repoRoot, sourceFile)}: cannot resolve image "${target}"`);
    }
    const generatedImage = path.join(generatedImagesDir, path.relative(imagesDir, resolved));
    const relativeImage = path.relative(path.dirname(outFile), generatedImage).split(path.sep).join('/');
    return `${relativeImage}${suffix}`;
  }

  const doc = docs.get(resolved);
  if (!doc) {
    throw new Error(
      `${path.relative(repoRoot, sourceFile)}: cannot resolve "${target}". ` +
        'Only pages inside docs/, and images inside docs/images/, can be linked from the user guide.',
    );
  }

  // Shared pages resolve to the copy in the current guide. A page that only exists
  // in the other sector guide is a deliberate cross-guide reference.
  const permalink = doc.sectorPermalinks?.[sector]
    ?? Object.values(doc.sectorPermalinks ?? {})[0]
    ?? doc.permalink;
  if (!permalink) {
    throw new Error(`${path.relative(repoRoot, sourceFile)}: "${target}" is not published in any guide`);
  }

  const relativeUrl = path.posix.relative(path.posix.dirname(pageUrl), permalink);

  return `${relativeUrl === '' ? '.' : relativeUrl}${suffix}`;
}

/**
 * @param {{sourceFile: string, outFile: string, pageUrl: string, sector: string}} page
 */
function renderPage({sourceFile, outFile, pageUrl, sector}) {
  const content = fs.readFileSync(sourceFile, 'utf8');
  return content.replace(markdownLink, (match, bang, text, target, title = '') =>
    `${bang}[${text}](${rewriteTarget({image: bang === '!', target, sourceFile, pageUrl, outFile, sector})}${title})`,
  );
}

/** @type {{file: string, content: Buffer}[]} */
const writes = [];

/** @param {string} file @param {string|Buffer} content */
function writeGenerated(file, content) {
  writes.push({file, content: Buffer.isBuffer(content) ? content : Buffer.from(content, 'utf8')});
}

function generate() {
  registerDocumentationPages();
  registerGuidePages();

  for (const sector of sectors) {
    for (const [sourceFile, rel] of sectorPages(sector)) {
      const outFile = path.join(generatedDir, 'user-guide', sector, rel);
      const pageUrl = `${guideRouteBase}/${sector}/${rel.replace(/\.mdx?$/, '')}`;
      writeGenerated(outFile, renderPage({sourceFile, outFile, pageUrl, sector}));
    }
  }

  for (const file of fs.readdirSync(imagesDir)) {
    writeGenerated(
      path.join(generatedImagesDir, file),
      fs.readFileSync(path.join(imagesDir, file)),
    );
  }
}

function sync() {
  generate();

  const stale = [];
  for (const {file, content} of writes) {
    const current = fs.existsSync(file) ? fs.readFileSync(file) : null;
    if (current === null || !current.equals(content)) {
      stale.push(file);
      if (!checkOnly) {
        fs.mkdirSync(path.dirname(file), {recursive: true});
        fs.writeFileSync(file, content);
      }
    }
  }

  if (checkOnly) {
    if (stale.length > 0) {
      console.error(
        `The generated guides are out of date:\n${stale.map((file) => `  - ${path.relative(siteDir, file)}`).join('\n')}`,
      );
      console.error('Run "npm run guides" in docs-site/ to regenerate them.');
      process.exit(1);
    }
    console.log(`Generated guides are up to date (${writes.length} files).`);
    return;
  }

  console.log(`Generated ${writes.length} files for the Private and Government user guides.`);
}

sync();
