# Wanda — Business Assistant Bot

A Telegram bot built with Laravel that connects to a real-world business management web application, allowing you to query and register income, expenses and transactions directly from your phone — no need to open the app.

Built for a business that manages daily income, expenses, and repair orders through an internal web platform.

---

## What Wanda can do

### 📊 Monthly Summary
Ask Wanda for a summary of the current month and get:
- Total income
- Total expenses
- Credit expenses breakdown
- Balance

### 📝 Register a new transaction
Wanda walks you through a step-by-step conversation to log a new income or expense entry, including:
- Type (income or expense)
- Amount
- Description
- Category & subcategory
- Project
- Date

### ❌ Error handling
If the business server is unavailable, Wanda responds with a clear error message instead of silently failing.

### 🚫 Cancel anytime
Type `cancelar` at any point during a conversation to cancel the current operation.

---

## Tech stack

| Layer | Technology |
|---|---|
| Bot backend | Laravel 12 (PHP) |
| Business API | Laravel 12 (PHP) |
| Database | MySQL |
| Messaging | Telegram Bot API |
| Local tunneling (dev) | ngrok |

---

## Architecture

```
Telegram
    ↕
Wanda Bot (Laravel)
    ↕
Business App API (Laravel)
    ↕
MySQL Database
```

Wanda communicates with the business application through a secured REST API using a private token. It never connects directly to the database.

---

## Security

- All API endpoints are protected with a secret token via a custom `X-Wanda-Token` header
- The business API is not publicly exposed
- No sensitive business data is stored in the bot

---

## Roadmap

- [ ] Query by specific month or year
- [ ] Natural language understanding via Claude API
- [ ] Automated weekly summaries
- [ ] Deploy to self-hosted Raspberry Pi server
- [ ] Migrate from Telegram to WhatsApp (Twilio)

---

## Setup

### Requirements
- PHP 8.2+
- Composer
- A Telegram bot token (via [@BotFather](https://t.me/BotFather))
- A running instance of the business API

### Environment variables

```env
TELEGRAM_TOKEN=your-telegram-bot-token
WANDA_API_URL=https://your-business-app-url.com
WANDA_TOKEN=your-secret-token
```

### Register the webhook
```bash
curl "https://api.telegram.org/botYOUR_TOKEN/setWebhook?url=https://your-domain.com/api/telegram/webhook"
```

---

## Author

Gustavo Chavira — [github.com/gchaviraa](https://github.com/gchaviraa)
