# FIFA Arena Streaming Backend API

A production-ready, highly secure, and performance-optimized serverless PHP backend API for managing sports streaming channels and URLs. This backend runs on **Vercel Serverless Functions (PHP 8.2)** and connects to a **Supabase PostgreSQL** database.

---

## Technical Stack
- **API Runtime:** Vercel Serverless (using `vercel-php@0.6.0` builder)
- **Programming Language:** PHP 8.2 (Raw PDO PostgreSQL for zero cold-start overhead)
- **Database:** Supabase PostgreSQL (Managed DB)
- **Format:** RESTful JSON API

---

## 1. Supabase Database Schema Setup

Deploy the PostgreSQL table structure in your Supabase dashboard:
1. Navigate to your Supabase project dashboard: `https://supabase.com`
2. Open the **SQL Editor** from the left panel.
3. Click **New Query**.
4. Copy and paste the contents of [create_tables.sql](file:///d:/tv-apk/backend/sql/create_tables.sql):
```sql
CREATE TABLE IF NOT EXISTS channels (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    logo TEXT,
    category TEXT DEFAULT 'football',
    streams TEXT[] NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    priority INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_channels_is_active ON channels(is_active);
CREATE INDEX IF NOT EXISTS idx_channels_priority ON channels(priority DESC);
```
5. Click **Run** to execute the script and build the database.

---

## 2. Environment Variables (.env)

Before deploying or running the project, configure these environment variables:

| Variable | Description | Example Value |
|---|---|---|
| `SUPABASE_HOST` | Host address of your Supabase DB | `db.kiahbjcbantbyklccjyz.supabase.co` |
| `SUPABASE_PORT` | PostgreSQL connection port | `5432` |
| `SUPABASE_DB` | Database name | `postgres` |
| `SUPABASE_USER` | PostgreSQL Username | `postgres` |
| `SUPABASE_PASS` | Password | `Didwho12345@@` |

---

## 3. Vercel Deployment

Deploy directly using Vercel CLI from this directory:

### Step 1: Install Vercel CLI
If not already installed, run:
```bash
npm install -g vercel
```

### Step 2: Login and Link Project
Run the login command and link your GitHub repository:
```bash
vercel login
vercel link
```

### Step 3: Configure Environment Variables on Vercel
Set the database environment variables inside your Vercel Dashboard project settings under **Environment Variables**, or use Vercel CLI:
```bash
vercel env add SUPABASE_HOST db.kiahbjcbantbyklccjyz.supabase.co
vercel env add SUPABASE_PORT 5432
vercel env add SUPABASE_DB postgres
vercel env add SUPABASE_USER postgres
vercel env add SUPABASE_PASS "Didwho12345@@"
```

### Step 4: Deploy
Deploy the codebase to production:
```bash
vercel --prod
```
Once complete, Vercel will output your live URL (e.g., `https://your-project.vercel.app`).

---

## 4. API Endpoint curl Testing

### Health Check Endpoint
```bash
curl -X GET "https://your-project.vercel.app/api/index.php?action=health"
```

### Create a New Channel (POST)
```bash
curl -X POST "https://your-project.vercel.app/api/index.php" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "PTV Sports HD",
       "logo": "https://upload.wikimedia.org/wikipedia/en/2/23/PTV_Sports_logo.png",
       "streams": [
         "https://shls-live-enc.edgenextcdn.net/out/v1/ad35123d4ad041068bce26ae67ee3180/index.m3u8",
         "https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8"
       ],
       "priority": 10
     }'
```

### Retrieve All Active Channels (GET)
```bash
curl -X GET "https://your-project.vercel.app/api/index.php"
```

### Retrieve a Single Channel (GET)
```bash
curl -X GET "https://your-project.vercel.app/api/index.php?id=1"
```

### Update a Channel (PUT)
```bash
curl -X PUT "https://your-project.vercel.app/api/index.php?id=1" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "PTV Sports Premium",
       "priority": 15
     }'
```

### Soft Delete a Channel (DELETE)
```bash
curl -X DELETE "https://your-project.vercel.app/api/index.php?id=1"
```
