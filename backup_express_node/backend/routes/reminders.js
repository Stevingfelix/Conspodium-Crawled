import express from 'express';
import db from '../db.js';

const router = express.Router();

// POST /api/reminders - Register email for event reminder
router.post('/reminders', (req, res) => {
  try {
    const { email, eventName = 'The Future of African Democracy' } = req.body;

    if (!email || !email.includes('@')) {
      return res.status(400).json({ success: false, error: 'A valid email address is required' });
    }

    db.prepare(`INSERT INTO event_reminders (event_name, user_email) VALUES (?, ?)`).run(eventName, email);

    // Generate .ics calendar string
    const icsContent = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Conspodium//Discussion Event//EN',
      'BEGIN:VEVENT',
      `SUMMARY:${eventName}`,
      'DESCRIPTION:Live discussion featuring Prof. Amara Diallo (London School of Economics) on African multilateral governance.',
      'DTSTART:20261015T180000Z',
      'DTEND:20261015T193000Z',
      'LOCATION:Conspodium Digital Amphitheatre',
      'STATUS:CONFIRMED',
      'END:VEVENT',
      'END:VCALENDAR'
    ].join('\r\n');

    res.json({
      success: true,
      message: `Reminder set! We will notify ${email} before the event.`,
      icsDataUrl: `data:text/calendar;charset=utf8,${encodeURIComponent(icsContent)}`
    });
  } catch (err) {
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
