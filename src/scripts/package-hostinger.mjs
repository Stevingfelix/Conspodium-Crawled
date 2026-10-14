import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '../..');
const distDir = path.join(rootDir, 'dist');
const publicDir = path.join(rootDir, 'public');

if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

const zipPath = path.join(distDir, 'conspodium-hostinger-public_html.zip');
if (fs.existsSync(zipPath)) {
  fs.unlinkSync(zipPath);
}

console.log('📦 Packaging all public/ contents for Hostinger public_html direct deployment...');

// Zip all files including dotfiles (.htaccess) directly from public/
const zipCmd = `zip -r "${zipPath}" . -x "*.DS_Store" "*__MACOSX*"`;
execSync(zipCmd, { cwd: publicDir, stdio: 'pipe' });

const stats = fs.statSync(zipPath);
const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);
console.log(`\n🎉 Hostinger Deployment Package Ready!`);
console.log(`   Location: ${zipPath}`);
console.log(`   Size: ${sizeMB} MB`);
console.log(`   All files & folders sit directly at the root (ready for public_html).\n`);
