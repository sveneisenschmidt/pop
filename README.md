# Pop

Lightweight, self-hosted emoji reaction widget for blogs.

## Features

- Toggle reactions on/off (server remembers user state via fingerprinting)
- Rate limiting (10 requests/minute per user)
- CORS with domain whitelist
- SQLite storage (single file, no external database)
- ~2KB minified frontend

## Structure

```
pop/
├── frontend/     # TypeScript widget
└── backend/      # PHP/Symfony API
```

## Requirements

- Node.js 18+
- PHP 8.1+
- Composer

## Development

```bash
make install   # Install dependencies
make test      # Run tests (24 backend + 13 frontend)
make dist      # Build for production
```

## Demo

```bash
make demo
```

Opens http://localhost:8000/demo.html - click emojis to toggle reactions.

## Deployment

1. Build the project:
   ```bash
   make dist
   ```

2. The `build/` directory is a complete, self-contained application:
   ```
   build/
   ├── public/         # Document root
   │   ├── index.php   # Symfony entry point
   │   ├── pop.min.js  # Widget script
   │   └── pop.min.css # Widget styles
   ├── var/            # Database (must be writable)
   └── ...
   ```

3. Deploy `build/` to your PHP server with `public/` as document root.

4. Configure `build/.env`:
   ```bash
   APP_ENV=prod
   APP_SECRET=<random-string>
   POP_ALLOWED_DOMAINS=https://your-blog.com
   POP_DATABASE_PATH=var/data.db
   ```

5. Ensure `var/` directory is writable by the web server.

## Usage

```html
<link rel="stylesheet" href="https://your-api.com/pop.min.css">
<div id="pop"></div>
<script src="https://your-api.com/pop.min.js"></script>
<script>
  Pop.init({
    el: '#pop',
    endpoint: 'https://your-api.com/api',
    pageId: 'unique-page-id',  // optional, defaults to current URL
    emojis: ['👍', '🔥', '💡', '❤️']
  });
</script>
```

## API

### GET /api/reactions?pageId={id}

Returns reaction counts and current user's reactions.

```json
{
  "pageId": "my-page",
  "reactions": { "👍": 5, "🔥": 3 },
  "userReactions": ["👍"]
}
```

### POST /api/reactions

Toggles a reaction (adds if not present, removes if already added).

Request:
```json
{ "pageId": "my-page", "emoji": "👍" }
```

Response:
```json
{ "success": true, "action": "added", "count": 6 }
```

## Configuration

| Variable | Description |
|----------|-------------|
| `APP_SECRET` | Symfony secret key |
| `POP_ALLOWED_DOMAINS` | Comma-separated allowed origins |
| `POP_DATABASE_PATH` | Path to SQLite database file |

## License

MIT
