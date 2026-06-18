const { createClient } = require('@supabase/supabase-js');

const supabase = createClient(
  process.env.SUPABASE_URL,
  process.env.SUPABASE_ANON_KEY
);

module.exports = async (req, res) => {
  // Basic Rate Limiting (Demo purpose - in production use Vercel's Edge Config or external service)
  // For now, we rely on Vercel's built-in protection and structured query handling

  const { country, category } = req.query;

  try {
    let query = supabase
      .from('channels')
      .select('*')
      .eq('is_active', true);

    if (country) {
      query = query.ilike('country', `%${country}%`);
    }

    if (category) {
      query = query.eq('category', category.toLowerCase());
    }

    const { data, error } = await query.order('name', { ascending: true });

    if (error) throw error;

    return res.status(200).json({
      success: true,
      data: data
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      message: error.message
    });
  }
};
