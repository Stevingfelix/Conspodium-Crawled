import express from 'express';
import db from '../db.js';

const router = express.Router();

function slugify(text) {
  return text.toString().toLowerCase()
    .trim()
    .replace(/\s+/g, '-')           // Replace spaces with -
    .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
    .replace(/\-\-+/g, '-');        // Replace multiple - with single -
}

// GET /api/posts - Get list of posts with filtering & pagination
router.get('/posts', (req, res) => {
  try {
    const { category, search, featured, limit = 50, offset = 0 } = req.query;
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

// GET /api/posts/:slugOrId - Get single post detail by slug or ID
router.get('/posts/:slugOrId', (req, res) => {
  try {
    const { slugOrId } = req.params;
    const isId = /^\d+$/.test(slugOrId);
    
    const query = isId
      ? `SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?`
      : `SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ?`;

    const post = db.prepare(query).get(slugOrId);

    if (!post) {
      return res.status(404).json({ success: false, error: 'Article not found' });
    }

    // Increment view count
    db.prepare(`UPDATE posts SET views = views + 1 WHERE id = ?`).run(post.id);
    post.views += 1;

    const transcript = db.prepare(`SELECT * FROM transcripts WHERE post_id = ?`).get(post.id);

    res.json({ success: true, post, transcript: transcript ? JSON.parse(transcript.transcript_content) : null });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// POST /api/posts - Create a new post
router.post('/posts', (req, res) => {
  try {
    const { title, eyebrow, excerpt, content, categoryId, authorName, authorAvatar, featuredImage, readingTime, isFeatured, publishedAt } = req.body;

    if (!title || !content) {
      return res.status(400).json({ success: false, error: 'Title and content are required' });
    }

    let slug = slugify(title);
    // Ensure unique slug
    const existing = db.prepare(`SELECT id FROM posts WHERE slug = ?`).get(slug);
    if (existing) {
      slug += '-' + Date.now().toString().slice(-4);
    }

    const pubDate = publishedAt ? new Date(publishedAt).toISOString().replace('T', ' ').slice(0, 19) : new Date().toISOString().replace('T', ' ').slice(0, 19);

    const result = db.prepare(`
      INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
    `).run(
      title,
      slug,
      eyebrow || 'Community Essay',
      excerpt || title,
      content,
      categoryId ? parseInt(categoryId) : null,
      authorName || 'Conspodium Editorial',
      authorAvatar || 'CP',
      featuredImage || './wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg',
      readingTime || '5 min read',
      isFeatured ? 1 : 0,
      pubDate
    );

    res.json({
      success: true,
      postId: result.lastInsertRowid,
      slug,
      message: 'Article created successfully!'
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// PUT /api/posts/:id - Edit an existing post
router.put('/posts/:id', (req, res) => {
  try {
    const { id } = req.params;
    const { title, eyebrow, excerpt, content, categoryId, authorName, authorAvatar, featuredImage, readingTime, isFeatured, publishedAt } = req.body;

    const existing = db.prepare(`SELECT * FROM posts WHERE id = ?`).get(id);
    if (!existing) {
      return res.status(404).json({ success: false, error: 'Article not found' });
    }

    let slug = existing.slug;
    if (title && title !== existing.title) {
      slug = slugify(title);
      const checkSlug = db.prepare(`SELECT id FROM posts WHERE slug = ? AND id != ?`).get(slug, id);
      if (checkSlug) slug += '-' + Date.now().toString().slice(-4);
    }

    const pubDate = publishedAt ? new Date(publishedAt).toISOString().replace('T', ' ').slice(0, 19) : existing.published_at;

    db.prepare(`
      UPDATE posts
      SET title = ?, slug = ?, eyebrow = ?, excerpt = ?, content = ?, category_id = ?, author_name = ?, author_avatar = ?, featured_image = ?, reading_time = ?, is_featured = ?, published_at = ?
      WHERE id = ?
    `).run(
      title || existing.title,
      slug,
      eyebrow !== undefined ? eyebrow : existing.eyebrow,
      excerpt !== undefined ? excerpt : existing.excerpt,
      content || existing.content,
      categoryId !== undefined ? (categoryId ? parseInt(categoryId) : null) : existing.category_id,
      authorName || existing.author_name,
      authorAvatar || existing.author_avatar,
      featuredImage || existing.featured_image,
      readingTime || existing.reading_time,
      isFeatured !== undefined ? (isFeatured ? 1 : 0) : existing.is_featured,
      pubDate,
      id
    );

    res.json({ success: true, message: 'Article updated successfully!' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// DELETE /api/posts/:id - Delete a post
router.delete('/posts/:id', (req, res) => {
  try {
    const { id } = req.params;
    db.prepare(`DELETE FROM posts WHERE id = ?`).run(id);
    res.json({ success: true, message: 'Article deleted successfully' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// ── Category Routes ──────────────────────────────────────────────────────────

// GET /api/categories - Get categories
router.get('/categories', (req, res) => {
  try {
    const categories = db.prepare(`
      SELECT c.*, COUNT(p.id) as post_count
      FROM categories c
      LEFT JOIN posts p ON c.id = p.category_id
      GROUP BY c.id
      ORDER BY c.name ASC
    `).all();

    res.json({ success: true, categories });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// POST /api/categories - Add a new category
router.post('/categories', (req, res) => {
  try {
    const { name, icon, description } = req.body;
    if (!name) return res.status(400).json({ success: false, error: 'Category name is required' });

    const slug = slugify(name);
    const result = db.prepare(`INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)`).run(
      name, slug, icon || '🏷️', description || ''
    );

    res.json({ success: true, categoryId: result.lastInsertRowid, message: 'Category created successfully' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// PUT /api/categories/:id - Update category
router.put('/categories/:id', (req, res) => {
  try {
    const { id } = req.params;
    const { name, icon, description } = req.body;

    const existing = db.prepare(`SELECT * FROM categories WHERE id = ?`).get(id);
    if (!existing) return res.status(404).json({ success: false, error: 'Category not found' });

    const slug = name ? slugify(name) : existing.slug;
    db.prepare(`UPDATE categories SET name = ?, slug = ?, icon = ?, description = ? WHERE id = ?`).run(
      name || existing.name, slug, icon || existing.icon, description !== undefined ? description : existing.description, id
    );

    res.json({ success: true, message: 'Category updated successfully' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// DELETE /api/categories/:id - Delete category
router.delete('/categories/:id', (req, res) => {
  try {
    const { id } = req.params;
    db.prepare(`DELETE FROM categories WHERE id = ?`).run(id);
    res.json({ success: true, message: 'Category deleted successfully' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
