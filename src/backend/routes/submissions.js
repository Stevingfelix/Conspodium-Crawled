import express from 'express';
import db from '../db.js';

const router = express.Router();

function slugify(text) {
  return text.toString().toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-');
}

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

// PUT /api/submissions/:id/approve - Approve & publish submission into posts table
router.post('/submissions/:id/approve', (req, res) => {
  try {
    const { id } = req.params;
    const { categoryId, featuredImage } = req.body;

    const submission = db.prepare(`SELECT * FROM story_submissions WHERE id = ?`).get(id);
    if (!submission) {
      return res.status(404).json({ success: false, error: 'Submission not found' });
    }

    let slug = slugify(submission.title);
    const existingSlug = db.prepare(`SELECT id FROM posts WHERE slug = ?`).get(slug);
    if (existingSlug) slug += '-' + Date.now().toString().slice(-4);

    const authorInitials = submission.author_name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'CP';

    // Insert into posts table
    const postResult = db.prepare(`
      INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
      VALUES (?, ?, 'Community Voice', ?, ?, ?, ?, ?, ?, '6 min read', 0, 0, CURRENT_TIMESTAMP)
    `).run(
      submission.title,
      slug,
      submission.content.slice(0, 160) + '...',
      `<p>${submission.content.replace(/\n\n/g, '</p><p>')}</p>`,
      categoryId ? parseInt(categoryId) : 1,
      submission.author_name,
      authorInitials,
      featuredImage || './wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg'
    );

    // Update submission status to 'approved'
    db.prepare(`UPDATE story_submissions SET status = 'approved' WHERE id = ?`).run(id);

    res.json({
      success: true,
      postId: postResult.lastInsertRowid,
      message: 'Submission approved and published as a live article!'
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// PUT /api/submissions/:id/reject - Reject submission
router.post('/submissions/:id/reject', (req, res) => {
  try {
    const { id } = req.params;
    db.prepare(`UPDATE story_submissions SET status = 'rejected' WHERE id = ?`).run(id);
    res.json({ success: true, message: 'Submission marked as rejected' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// DELETE /api/submissions/:id - Delete submission
router.delete('/submissions/:id', (req, res) => {
  try {
    const { id } = req.params;
    db.prepare(`DELETE FROM story_submissions WHERE id = ?`).run(id);
    res.json({ success: true, message: 'Submission deleted' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
