import { mkdir, cp } from 'fs/promises';
import { resolve } from 'path';

const sourceDir = resolve('resources/css/themes');
const targetDir = resolve('dist/css/themes');

await mkdir(targetDir, { recursive: true });
await cp(sourceDir, targetDir, { recursive: true });

console.log('AuthKit theme CSS copied to dist/css/themes');