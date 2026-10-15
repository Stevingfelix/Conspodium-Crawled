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

// 1.5. Validate JavaScript syntax in dashboard to prevent broken updates
console.log('  1.5. Validating JavaScript syntax in public/dashboard/index.html...');
const dashHtml = fs.readFileSync(path.join(rootDir, 'public/dashboard/index.html'), 'utf8');
const scriptRegex = /<script(?:\s+[^>]*)?>([\s\S]*?)<\/script>/gi;
let match;
let scriptIdx = 0;
while ((match = scriptRegex.exec(dashHtml)) !== null) {
  scriptIdx++;
  const code = match[1];
  if (!code.trim()) continue;
  try {
    new Function(code);
  } catch (err) {
    console.error(`❌ FATAL BUILD ERROR: Syntax Error in Dashboard Script ${scriptIdx}:`, err.message);
    process.exit(1);
  }
}
console.log('       ✓ Dashboard JavaScript syntax 100% verified.');

// 2. Ensure dist directory exists
if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

// 3. Read or preserve dynamic version.json metadata
let versionData = {
  version: '2.1.4',
  description: 'Notification dropdown modernization, stacked actions, and maintenance popup safeguards.',
  author: 'Conspodium Core Team',
  type: 'lightweight_feature_patch',
  highlights: [
    'Removed Forum Moderation item from notification dropdown list',
    'Vertically stacked bottom buttons with single-line labels: View All Article Comments & View All Forum Discussions',
    'Suppressed promo popup adverts from appearing while site is in Maintenance Mode',
    'Enhanced System Updates card with active release summary and highlights'
  ]
};
try {
  if (fs.existsSync(path.join(rootDir, 'version.json'))) {
    const existing = JSON.parse(fs.readFileSync(path.join(rootDir, 'version.json'), 'utf8'));
    versionData = { ...versionData, ...existing };
  }
} catch (e) {}

const now = new Date();
versionData.build_timestamp = now.toISOString().replace(/T/, ' ').replace(/\..+/, '');
fs.writeFileSync(path.join(rootDir, 'version.json'), JSON.stringify(versionData, null, 2));

const zipFileName = `conspodium-update-v${versionData.version}.zip`;
const zipFilePath = path.join(distDir, zipFileName);

// Remove existing zip if present
if (fs.existsSync(zipFilePath)) {
  fs.unlinkSync(zipFilePath);
}

// 4. Create ZIP package using zip command
console.log(`  2. Creating secure update package (${zipFileName})...`);

const zipCmd = `zip -r "${zipFilePath}" public api src version.json router.php install.php .htaccess -x "api/config.php" "data/*" "api/*.db" "api/*.sqlite" "*uploads/*" "*.mp4" "*.webm" "*.mov" "*.avi" "*videos/*" "node_modules/*" ".git/*" ".DS_Store" "*__MACOSX*"`;

try {
  execSync(zipCmd, { cwd: rootDir, stdio: 'pipe' });
  const stats = fs.statSync(zipFilePath);
  const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);
  console.log(`       ✓ Zip package written (${sizeMB} MB).`);

  // 5. Automated Isolated Sandbox Testing
  console.log(`\n  3. Executing isolated sandbox test verification on ${zipFileName}...`);
  try {
    execSync(`node "${path.join(__dirname, 'test-update-package.mjs')}" "${zipFilePath}"`, {
      cwd: rootDir,
      stdio: 'inherit'
    });
  } catch (testErr) {
    console.error(`\n❌ FATAL: Sandbox test verification failed for ${zipFileName}!`);
    console.error(`Removing failed package to prevent accidental deployment.`);
    if (fs.existsSync(zipFilePath)) fs.unlinkSync(zipFilePath);
    process.exit(1);
  }

  console.log(`\n✅ Update Package Created & 100% Sandbox Tested!`);
  console.log(`   Location: ${zipFilePath}`);
  console.log(`   File Size: ${sizeMB} MB`);
  console.log(`   Safeguards: MySQL config, SQLite DB & Media Uploads are 100% protected.`);
  console.log(`   Integrity: PHP linted, JS validated, DOM hooks verified in sandbox.\n`);
  console.log(`👉 To update your live site, go to Admin Dashboard → Settings → System Updates and upload this verified zip file.\n`);
} catch (err) {
  console.error('❌ Error creating zip package:', err.message);
  process.exit(1);
}
