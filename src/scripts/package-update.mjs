// src/scripts/package-update.mjs
// Automates building and packaging update ZIPs for Hostinger in-app deployment.

import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '../..');
const distDir = path.join(rootDir, 'dist');

console.log('\n📦 Conspodium Update Packaging Engine\n');

// 1. Run fresh build
console.log('  1. Running fresh site build (node src/scripts/build.mjs)...');
execSync('node src/scripts/build.mjs', { cwd: rootDir, stdio: 'inherit' });

// 2. Ensure dist directory exists
if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

// 3. Update version.json
const now = new Date();
const timestamp = now.toISOString().replace(/T/, ' ').replace(/\..+/, '');
const versionData = {
  version: '2.1.0',
  build_timestamp: timestamp,
  description: 'Conspodium System & Frontend Update Package',
  author: 'Conspodium Core Team'
};
fs.writeFileSync(path.join(rootDir, 'version.json'), JSON.stringify(versionData, null, 2));

const zipFileName = `conspodium-update-v2.1.0.zip`;
const zipFilePath = path.join(distDir, zipFileName);

// Remove existing zip if present
if (fs.existsSync(zipFilePath)) {
  fs.unlinkSync(zipFilePath);
}

// 4. Create ZIP package using zip command
console.log(`  2. Creating secure update package (${zipFileName})...`);

const zipCmd = `zip -r "${zipFilePath}" public api src version.json router.php -x "api/config.php" "data/*" "api/*.db" "api/*.sqlite" "public/wp-content/uploads/*" "public/uploads/*" "node_modules/*" ".git/*" ".DS_Store" "*__MACOSX*"`;

try {
  execSync(zipCmd, { cwd: rootDir, stdio: 'pipe' });
  const stats = fs.statSync(zipFilePath);
  const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);
  
  console.log(`\n✅ Update Package Created Successfully!`);
  console.log(`   Location: ${zipFilePath}`);
  console.log(`   File Size: ${sizeMB} MB`);
  console.log(`   Safeguards: MySQL config, SQLite DB & Media Uploads are excluded.\n`);
  console.log(`👉 To update your live site, go to Admin Dashboard → Settings → System Updates and upload this zip file.\n`);
} catch (err) {
  console.error('❌ Error creating zip package:', err.message);
  process.exit(1);
}
