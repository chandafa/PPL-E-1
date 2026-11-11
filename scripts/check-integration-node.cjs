if (Number(process.versions.node.split('.')[0]) < 18) {
    console.error('F-1 memerlukan Node.js >=18 melalui nvm.');
    process.exit(1);
}
if (process.argv.includes('--frontend')) {
    const [major, minor] = process.versions.node.split('.').map(Number);
    if (!((major === 20 && minor >= 19) || (major === 22 && minor >= 12) || major > 22)) {
        console.error('Build Vite memerlukan Node.js 20.19+, 22.12+, atau lebih baru. Aktifkan Node 24 melalui nvm.');
        process.exit(1);
    }
}
