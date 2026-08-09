import express from 'express';
import db from '../db.js';

const router = express.Router();

// GET /api/posts - Get list of posts with filtering & pagination
router.get('/posts', (req, res) => {
  try {
    const { category, search, featured, limit = 10, offset = 0 } = req.query;
    let query = `
      SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
      FROM posts p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE 1=1
    `;
    const params = [];

    if (category) {
      query += ` AND (c.slug = ? OR c.name LIKE ?)`;
      params.push(category, `%${category}%`);
    }

    if (search) {
      query += ` AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)`;
      params.push(`%${search}%`, `%${search}%`, `%${search}%`);
    }

    if (featured === '1' || featured === 'true') {
      query += ` AND p.is_featured = 1`;
    }

    query += ` ORDER BY p.published_at DESC LIMIT ? OFFSET ?`;
    params.push(parseInt(limit), parseInt(offset));

    const posts = db.prepare(query).all(...params);
    const total = db.prepare(`SELECT COUNT(*) as count FROM posts`).get().count;

    res.json({ success: true, posts, total, limit: parseInt(limit), offset: parseInt(offset) });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// GET /api/posts/:slug - Get single post detail & increment views
router.get('/posts/:slug', (req, res) => {
  try {
    const { slug } = req.params;
    const post = db.prepare(`
      SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
      FROM posts p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.slug = ?
    `).get(slug);

    if (!post) {
      return res.status(404).json({ success: false, error: 'Article not found' });
    }

    // Increment view count
    db.prepare(`UPDATE posts SET views = views + 1 WHERE id = ?`).run(post.id);
    post.views += 1;

    // Check if transcript exists
    const transcript = db.prepare(`SELECT * FROM transcripts WHERE post_id = ?`).get(post.id);

    res.json({ success: true, post, transcript: transcript ? JSON.parse(transcript.transcript_content) : null });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// GET /api/categories - Get all categories with post counts
router.get('/categories', (req, res) => {
  try {
    const categories = db.prepare(`
      SELECT c.*, COUNT(p.id) as post_count
      FROM categories c
      LEFT JOIN posts p ON c.id = p.category_id
      GROUP BY c.id
      ORDER BY post_count DESC
    `).all();

    res.json({ success: true, categories });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
