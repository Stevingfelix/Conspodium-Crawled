import Database from 'better-sqlite3';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { mkdirSync, existsSync } from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DATA_DIR  = join(__dirname, '../../data');

if (!existsSync(DATA_DIR)) {
  mkdirSync(DATA_DIR, { recursive: true });
}

const DB_PATH = join(DATA_DIR, 'conspodium.db');
const db = new Database(DB_PATH);

// Enable WAL mode for better performance
db.pragma('journal_mode = WAL');

export function initDb() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      slug TEXT UNIQUE NOT NULL,
      icon TEXT,
      description TEXT
    );

    CREATE TABLE IF NOT EXISTS posts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      slug TEXT UNIQUE NOT NULL,
      eyebrow TEXT,
      excerpt TEXT,
      content TEXT NOT NULL,
      category_id INTEGER REFERENCES categories(id),
      author_name TEXT NOT NULL,
      author_avatar TEXT,
      featured_image TEXT,
      reading_time TEXT DEFAULT '5 min read',
      views INTEGER DEFAULT 0,
      is_featured BOOLEAN DEFAULT 0,
      published_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS transcripts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      post_id INTEGER REFERENCES posts(id),
      audio_url TEXT,
      speaker_name TEXT,
      transcript_content TEXT
    );

    CREATE TABLE IF NOT EXISTS polls (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      question TEXT NOT NULL,
      options_json TEXT NOT NULL,
      is_active BOOLEAN DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS poll_votes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      poll_id INTEGER REFERENCES polls(id),
      option_index INTEGER NOT NULL,
      voter_ip TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS story_submissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      author_name TEXT NOT NULL,
      author_email TEXT NOT NULL,
      author_bio TEXT,
      title TEXT NOT NULL,
      content TEXT NOT NULL,
      attachment_url TEXT,
      status TEXT DEFAULT 'pending',
      submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS event_reminders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      event_name TEXT NOT NULL,
      user_email TEXT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
  `);

  // Seed initial data if empty
  seedInitialData();
}

function seedInitialData() {
  const catCount = db.prepare('SELECT COUNT(*) as count FROM categories').get().count;
  if (catCount === 0) {
    console.log('🌱 Seeding initial database categories...');
    const insertCat = db.prepare('INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)');
    
    insertCat.run('Democracy & Politics', 'democracy-politics', '🏛️', 'Multilateral governance and political sovereignty across the diaspora.');
    insertCat.run('AI & Data Ethics', 'ai-data-ethics', '🤖', 'African data sovereignty, algorithm ethics, and diaspora tech innovation.');
    insertCat.run('Climate & Environment', 'climate-environment', '🌿', 'Grassroots climate justice, indigenous knowledge, and sustainability.');
    insertCat.run('Arts & Philosophy', 'arts-philosophy', '📚', 'Decolonising education, pan-African philosophy, and cultural literature.');
    insertCat.run('Biotechnology', 'biotechnology', '🧬', 'Scientific independence, healthcare innovation, and biotechnology in Africa.');
  }

  const postCount = db.prepare('SELECT COUNT(*) as count FROM posts').get().count;
  if (postCount === 0) {
    console.log('🌱 Seeding initial database posts...');
    const insertPost = db.prepare(`
      INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);

    insertPost.run(
      'The Digital Sovereignty Crisis: Who Controls Africa’s Data Future?',
      'digital-sovereignty-crisis-africas-data-future',
      "This Week's Featured Essay",
      'A landmark investigation into how global tech giants are shaping African digital policy — and what diaspora leaders are doing to fight back.',
      '<p>Across Africa and its global diaspora, a quiet battle for digital self-determination is underway. As foreign technology conglomerates expand data centers and cloud infrastructure, questions of data governance, privacy rights, and algorithmic bias have reached a critical tipping point.</p><p>African scholars and diaspora technologists are pioneering open frameworks that ensure data generated on the continent empowers local communities rather than extracting value abroad.</p>',
      2,
      'Dr. Kemi Adebayo',
      'KA',
      './wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg',
      '8 min read',
      1204,
      1
    );

    insertPost.run(
      'A New Perspective on Global Leadership',
      'new-perspective-on-global-leadership',
      'Coming Soon',
      'Prof. Amara Diallo of the London School of Economics shares his groundbreaking framework for African-led multilateral governance in the digital age.',
      '<p>Global governance models inherited from the 20th century are increasingly ill-equipped to address global challenges. Prof. Amara Diallo proposes a restructured pan-African diplomatic framework prioritizing youth representation, economic integration, and digital sovereignty.</p>',
      1,
      'Prof. Amara Diallo',
      'AD',
      './wp-content/uploads/2026/01/portrait-two-friends-holding-each-other-city-scaled.jpg',
      '6 min read',
      987,
      0
    );

    insertPost.run(
      'In Conversation with Dr. Ngozi Eze on Biotechnology',
      'in-conversation-with-dr-ngozi-eze',
      'Exclusive Interview',
      '"Biotechnology is the next frontier of African liberation. We must own our science, our data, and our story." — Dr. Ngozi Eze, MIT Media Lab.',
      '<p>In this exclusive interview, Dr. Ngozi Eze explores how bio-manufacturing, genetic research ethics, and diaspora-backed laboratories are transforming healthcare self-reliance in West Africa.</p>',
      5,
      'Dr. Ngozi Eze',
      'NE',
      './wp-content/uploads/2026/01/couple-using-technology-while-traveling-city-scaled.jpg',
      '12 min read',
      834,
      0
    );
  }

  const pollCount = db.prepare('SELECT COUNT(*) as count FROM polls').get().count;
  if (pollCount === 0) {
    console.log('🌱 Seeding active weekly poll...');
    const insertPoll = db.prepare('INSERT INTO polls (question, options_json, is_active) VALUES (?, ?, 1)');
    const options = JSON.stringify([
      'AI Ethics & African Data Sovereignty',
      'Global Political Representation',
      'Climate Justice & African Communities',
      'Philosophy & Decolonising Education'
    ]);
    const pollResult = insertPoll.run('What is the most pressing issue facing the African diaspora today?', options);
    
    // Seed initial votes
    const pollId = pollResult.lastInsertRowid;
    const insertVote = db.prepare('INSERT INTO poll_votes (poll_id, option_index, voter_ip) VALUES (?, ?, ?)');
    for (let i = 0; i < 312; i++) insertVote.run(pollId, 0, `192.168.1.${i}`);
    for (let i = 0; i < 198; i++) insertVote.run(pollId, 1, `192.168.2.${i}`);
    for (let i = 0; i < 271; i++) insertVote.run(pollId, 2, `192.168.3.${i}`);
    for (let i = 0; i < 145; i++) insertVote.run(pollId, 3, `192.168.4.${i}`);
  }

  const transcriptCount = db.prepare('SELECT COUNT(*) as count FROM transcripts').get().count;
  if (transcriptCount === 0) {
    console.log('🌱 Seeding sample interview transcript...');
    const insertTranscript = db.prepare('INSERT INTO transcripts (post_id, audio_url, speaker_name, transcript_content) VALUES (?, ?, ?, ?)');
    insertTranscript.run(
      3,
      './wp-content/uploads/2026/01/sample-interview-audio.mp3',
      'Dr. Ngozi Eze',
      JSON.stringify([
        { time: '00:00', speaker: 'Interviewer', text: 'Welcome Dr. Eze. How do you view the state of biotechnology investments in Africa?' },
        { time: '00:45', speaker: 'Dr. Ngozi Eze', text: 'Biotechnology is not a luxury; it is foundational to our healthcare independence. When we own our genomics data and diagnostic tools, we safeguard our future.' },
        { time: '02:15', speaker: 'Interviewer', text: 'What role can the diaspora play in accelerating this vision?' },
        { time: '03:10', speaker: 'Dr. Ngozi Eze', text: 'Beyond financial capital, knowledge transfer and institutional partnerships are key. Connecting diaspora researchers directly with local African labs creates immense leverage.' }
      ])
    );
  }
}

export default db;
