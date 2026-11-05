const esbuild = require('esbuild');

const isWatch = process.argv.includes('--watch');

const commonOptions = {
    entryPoints: ['src/mediaplace/index.js'],
    bundle: true,
    outfile: 'assets/mediaplace.js',
    format: 'iife',
    target: ['es2020'],
    logLevel: 'info',
    legalComments: 'none',
};

async function run() {
    if (isWatch) {
        const ctx = await esbuild.context({
            ...commonOptions,
            minify: false,
            sourcemap: 'inline',
        });
        await ctx.watch();
        console.log('Watching src/mediaplace/ for changes (unminified, inline sourcemap)...');
    } else {
        await esbuild.build({
            ...commonOptions,
            minify: true,
            sourcemap: false,
        });
        console.log('Built assets/mediaplace.js');
    }
}

run().catch((err) => {
    console.error(err);
    process.exit(1);
});
