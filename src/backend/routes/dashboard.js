import express from 'express';
import db from '../db.js';

const router = express.Router();

// GET /api/dashboard/overview - Retrieve dashboard metrics & top content
router.get('/dashboard/overview', (req, res) => {
  try {
    const totalPosts = db.prepare(`SELECT COUNT(*) as count FROM posts`).get().count;
    const totalViews = db.prepare(`SELECT SUM(views) as count FROM posts`).get().count || 0;
    const totalSubmissions = db.prepare(`SELECT COUNT(*) as count FROM story_submissions`).get().count;
    const pendingSubmissions = db.prepare(`SELECT COUNT(*) as count FROM story_submissions WHERE status = 'pending'`).get().count;

    const topPosts = db.prepare(`
      SELECT p.id, p.title, p.slug, p.views, p.reading_time, c.name as category_name
      FROM posts p
      LEFT JOIN categories c ON p.category_id = c.id
      ORDER BY p.views DESC LIMIT 5
    `).all();

    const recentSubmissions = db.prepare(`
      SELECT id, author_name, title, status, submitted_at
      FROM story_submissions
      ORDER BY submitted_at DESC LIMIT 5
    `).all();

    res.json({
      success: true,
      stats: {
        totalPosts,
        totalViews,
        totalSubmissions,
        pendingSubmissions
      },
      topPosts,
      recentSubmissions
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
