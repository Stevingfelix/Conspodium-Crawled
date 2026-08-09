import express from 'express';
import db from '../db.js';

const router = express.Router();

// POST /api/submissions - Submit a new story / essay draft
router.post('/submissions', (req, res) => {
  try {
    const { name, email, bio, title, content, attachmentUrl } = req.body;

    if (!name || !email || !title || !content) {
      return res.status(400).json({ success: false, error: 'Name, email, title, and content are required' });
    }

    const result = db.prepare(`
      INSERT INTO story_submissions (author_name, author_email, author_bio, title, content, attachment_url, status)
      VALUES (?, ?, ?, ?, ?, ?, 'pending')
    `).run(name, email, bio || '', title, content, attachmentUrl || null);

    res.json({
      success: true,
      submissionId: result.lastInsertRowid,
      message: 'Your story has been successfully submitted for editorial review!'
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// GET /api/submissions - Get submissions list (Admin/Dashboard view)
router.get('/submissions', (req, res) => {
  try {
    const submissions = db.prepare(`SELECT * FROM story_submissions ORDER BY submitted_at DESC`).all();
    res.json({ success: true, submissions });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
