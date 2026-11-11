if (Number(process.versions.node.split('.')[0]) < 18) {
    console.error('F-1 memerlukan Node.js >=18 melalui nvm.');
    process.exit(1);
}
