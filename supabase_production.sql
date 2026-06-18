-- ==========================================
-- 1. TABLE DEFINITION
-- ==========================================
CREATE TABLE IF NOT EXISTS channels (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    country TEXT NOT NULL,
    category TEXT NOT NULL CHECK (category IN ('sports', 'news', 'entertainment', 'movies')),
    stream_url TEXT NOT NULL,
    logo_url TEXT,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- ==========================================
-- 2. INDEXES FOR PERFORMANCE
-- ==========================================
CREATE INDEX IF NOT EXISTS idx_channels_country ON channels(country);
CREATE INDEX IF NOT EXISTS idx_channels_category ON channels(category);
CREATE INDEX IF NOT EXISTS idx_channels_is_active ON channels(is_active);

-- ==========================================
-- 3. ROW LEVEL SECURITY (RLS)
-- ==========================================
ALTER TABLE channels ENABLE ROW LEVEL SECURITY;

-- Allow anyone to READ active channels
CREATE POLICY "Public read access for active channels"
ON channels FOR SELECT
USING (is_active = true);

-- ==========================================
-- 4. EXAMPLE DATA (100% LEGAL STREAMS)
-- ==========================================
INSERT INTO channels (name, country, category, stream_url, logo_url, description)
VALUES 
('Al Jazeera English', 'International', 'news', 'https://live-hls-web-aje.getaj.net/AJE/05.m3u8', 'https://upload.wikimedia.org/wikipedia/en/thumb/f/f2/Al_Jazeera_English_Logo.svg/1200px-Al_Jazeera_English_Logo.svg.png', 'World news around the clock.'),
('France 24 English', 'France', 'news', 'https://static.france24.com/live/f24_en.video/playlist.m3u8', 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/82/France_24_logo_2013.svg/1200px-France_24_logo_2013.svg.png', 'International news from the French perspective.'),
('Red Bull TV', 'International', 'sports', 'https://rbmn-live.akamaized.net/hls/live/590945/flavour/master.m3u8', 'https://upload.wikimedia.org/wikipedia/en/thumb/f/f5/Red_Bull_TV_logo.svg/1200px-Red_Bull_TV_logo.svg.png', 'High adrenaline action and adventure sports.'),
('NASA TV', 'USA', 'entertainment', 'https://ntv1.akamaized.net/hls/live/2014049/NASA-NTV1-HLS/master.m3u8', 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e5/NASA_logo.svg/1200px-NASA_logo.svg.png', 'Official NASA channel for space exploration.'),
('DW English', 'Germany', 'news', 'https://dwstream72.akamaized.net/hls/live/2014525/dwstream72/master.m3u8', 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d1/Deutsche_Welle_logo.svg/1200px-Deutsche_Welle_logo.svg.png', 'Germany''s international broadcaster.');
