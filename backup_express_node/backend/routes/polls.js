import express from 'express';
import db from '../db.js';

const router = express.Router();

// GET /api/polls - Get all polls (Admin view)
router.get('/polls', (req, res) => {
  try {
    const polls = db.prepare(`SELECT * FROM polls ORDER BY created_at DESC`).all();
    const formatted = polls.map(p => {
      const options = JSON.parse(p.options_json);
      const votes = db.prepare(`SELECT option_index, COUNT(*) as count FROM poll_votes WHERE poll_id = ? GROUP BY option_index`).all(p.id);
      const voteMap = {};
      let total = 0;
      votes.forEach(v => { voteMap[v.option_index] = v.count; total += v.count; });
      return {
        id: p.id,
        question: p.question,
        options: options.map((opt, idx) => ({ option: opt, index: idx, count: voteMap[idx] || 0 })),
        totalVotes: total,
        isActive: !!p.is_active,
        createdAt: p.created_at
      };
    });

    res.json({ success: true, polls: formatted });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// GET /api/polls/active - Get current active poll & results
router.get('/polls/active', (req, res) => {
  try {
    const poll = db.prepare(`SELECT * FROM polls WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1`).get();
    if (!poll) {
      return res.status(404).json({ success: false, error: 'No active poll found' });
    }

    const options = JSON.parse(poll.options_json);
    const votes = db.prepare(`
      SELECT option_index, COUNT(*) as count 
      FROM poll_votes 
      WHERE poll_id = ? 
      GROUP BY option_index
    `).all(poll.id);

    const voteMap = {};
    let totalVotes = 0;

    votes.forEach(v => {
      voteMap[v.option_index] = v.count;
      totalVotes += v.count;
    });

    const results = options.map((optText, idx) => {
      const count = voteMap[idx] || 0;
      const percentage = totalVotes > 0 ? Math.round((count / totalVotes) * 100) : 0;
      return { option: optText, index: idx, count, percentage };
    });

    const userIp = req.headers['x-forwarded-for'] || req.socket.remoteAddress || '127.0.0.1';
    const hasVoted = db.prepare(`SELECT 1 FROM poll_votes WHERE poll_id = ? AND voter_ip = ?`).get(poll.id, userIp);

    res.json({
      success: true,
      poll: {
        id: poll.id,
        question: poll.question,
        options: results,
        totalVotes,
        userHasVoted: !!hasVoted
      }
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// POST /api/polls - Create a new weekly poll
router.post('/polls', (req, res) => {
  try {
    const { question, options, isActive = true } = req.body;
    if (!question || !Array.isArray(options) || options.length < 2) {
      return res.status(400).json({ success: false, error: 'Question and at least 2 options are required' });
    }

    if (isActive) {
      db.prepare(`UPDATE polls SET is_active = 0`).run();
    }

    const result = db.prepare(`INSERT INTO polls (question, options_json, is_active) VALUES (?, ?, ?)`).run(
      question, JSON.stringify(options), isActive ? 1 : 0
    );

    res.json({ success: true, pollId: result.lastInsertRowid, message: 'Weekly poll created successfully' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// POST /api/polls/:id/activate - Set poll active
router.post('/polls/:id/activate', (req, res) => {
  try {
    const { id } = req.params;
    db.prepare(`UPDATE polls SET is_active = 0`).run();
    db.prepare(`UPDATE polls SET is_active = 1 WHERE id = ?`).run(id);
    res.json({ success: true, message: 'Poll activated' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// DELETE /api/polls/:id - Delete poll
router.delete('/polls/:id', (req, res) => {
  try {
    const { id } = req.params;
    db.prepare(`DELETE FROM poll_votes WHERE poll_id = ?`).run(id);
    db.prepare(`DELETE FROM polls WHERE id = ?`).run(id);
    res.json({ success: true, message: 'Poll deleted' });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

// POST /api/polls/vote - Submit a vote
router.post('/polls/vote', (req, res) => {
  try {
    const { pollId, optionIndex } = req.body;
    if (pollId === undefined || optionIndex === undefined) {
      return res.status(400).json({ success: false, error: 'pollId and optionIndex are required' });
    }

    const userIp = req.headers['x-forwarded-for'] || req.socket.remoteAddress || '127.0.0.1';

    const existing = db.prepare(`SELECT id FROM poll_votes WHERE poll_id = ? AND voter_ip = ?`).get(pollId, userIp);
    if (existing) {
      return res.status(409).json({ success: false, error: 'You have already voted in this poll' });
    }

    db.prepare(`INSERT INTO poll_votes (poll_id, option_index, voter_ip) VALUES (?, ?, ?)`).run(pollId, parseInt(optionIndex), userIp);

    const poll = db.prepare(`SELECT * FROM polls WHERE id = ?`).get(pollId);
    const options = JSON.parse(poll.options_json);
    const votes = db.prepare(`
      SELECT option_index, COUNT(*) as count 
      FROM poll_votes 
      WHERE poll_id = ? 
      GROUP BY option_index
    `).all(pollId);

    const voteMap = {};
    let totalVotes = 0;
    votes.forEach(v => {
      voteMap[v.option_index] = v.count;
      totalVotes += v.count;
    });

    const results = options.map((optText, idx) => {
      const count = voteMap[idx] || 0;
      const percentage = totalVotes > 0 ? Math.round((count / totalVotes) * 100) : 0;
      return { option: optText, index: idx, count, percentage };
    });

    res.json({
      success: true,
      message: 'Vote recorded successfully',
      poll: {
        id: poll.id,
        question: poll.question,
        options: results,
        totalVotes,
        userHasVoted: true
      }
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
