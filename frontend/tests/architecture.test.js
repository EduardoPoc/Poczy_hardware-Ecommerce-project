import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, extname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));

function imports(source) {
  return [...source.matchAll(/(?:\bfrom\s+|\bimport\s*(?:\(\s*)?)(['"])([^'"]+)\1/g)]
    .map((match) => match[2])
    .filter((specifier) => specifier.startsWith('.'));
}

function localFile(specifier, parent) {
  const pathname = specifier.split(/[?#]/)[0];
  const direct = resolve(dirname(parent), pathname);
  const file = existsSync(direct) ? direct : resolve(root, 'public', pathname);
  assert.ok(existsSync(file), `${relative(root, parent)} references missing ${specifier}`);
  return file;
}

function applicationGraph() {
  const modules = new Map();
  function visit(file) {
    if (modules.has(file) || !['.js', '.vue'].includes(extname(file))) return;
    const source = readFileSync(file, 'utf8');
    const dependencies = imports(source).map((specifier) => localFile(specifier, file));
    modules.set(file, { source, dependencies });
    dependencies.forEach(visit);
  }
  for (const entry of ['storefront', 'auth', 'admin']) {
    visit(resolve(root, `src/entrypoints/${entry}.js`));
  }
  return modules;
}

test('all HTML entries and local assets resolve after reorganizing the frontend', () => {
  const entrypoints = new Set();
  for (const name of readdirSync(root).filter((file) => file.endsWith('.html'))) {
    const file = resolve(root, name);
    const source = readFileSync(file, 'utf8');
    for (const tag of source.matchAll(/<(?:script|link|img)\b[^>]*>/g)) {
      const attribute = tag[0].match(/(?:src|href)="([^"#]+)"/);
      if (!attribute || !attribute[1].startsWith('.')) continue;
      const target = localFile(attribute[1], file);
      if (tag[0].includes('type="module"')) {
        assert.equal(dirname(target), resolve(root, 'src/entrypoints'));
        entrypoints.add(target);
      }
    }
  }
  assert.equal(entrypoints.size, 3);
  assert.ok(applicationGraph().size > 40);
});

test('shared modules do not depend on application features or layouts', () => {
  for (const [file, { dependencies }] of applicationGraph()) {
    if (!relative(root, file).startsWith('src/shared/')) continue;
    for (const dependency of dependencies) {
      assert.ok(
        relative(root, dependency).startsWith('src/shared/'),
        `${relative(root, file)} depends on ${relative(root, dependency)}`,
      );
    }
  }
});

test('domain modules contain no DOM, Vue state or HTTP calls', () => {
  for (const [file, { source }] of applicationGraph()) {
    if (!file.endsWith('/domain.js')) continue;
    assert.doesNotMatch(source, /\b(?:document|window|fetch|apiRequest)\b/, relative(root, file));
    assert.doesNotMatch(source, /from\s+['"]vue['"]/, relative(root, file));
  }
});
