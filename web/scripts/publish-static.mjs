import { cpSync, copyFileSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { resolve } from 'node:path';

const output = resolve('out');
const publicDirectory = resolve('../backend/public');

mkdirSync(publicDirectory, { recursive: true });
copyFileSync(resolve(output, 'index.html'), resolve(publicDirectory, 'task-index.html'));
cpSync(resolve(output, '_next'), resolve(publicDirectory, '_next'), { recursive: true, force: true });
for (const entry of readdirSync(output)) {
  if (entry === '_next' || entry === 'index.html') continue;
  const source = resolve(output, entry);
  if (statSync(source).isDirectory()) cpSync(source, resolve(publicDirectory, entry), { recursive: true, force: true });
  else copyFileSync(source, resolve(publicDirectory, entry));
}

console.log('Published the web client to backend/public for Apache.');
