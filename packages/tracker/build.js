import { build } from 'esbuild';

await Promise.all([
  build({ entryPoints: ['src/index.js'], bundle: true, minify: true, format: 'iife', globalName: 'PulseCROTracker', outfile: 'dist/tracker.iife.js', target: ['es2020'] }),
  build({ entryPoints: ['src/index.js'], bundle: true, minify: true, format: 'esm', outfile: 'dist/tracker.esm.js', target: ['es2020'] }),
]);
